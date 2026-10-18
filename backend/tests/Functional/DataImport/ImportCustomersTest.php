<?php

namespace App\Tests\Functional\DataImport;

final class ImportCustomersTest extends ImportTestCase
{
    private const HEADER = "first_name,last_name,phone,email,address,sales_rep_email,sms_consent,email_consent,consent_source,notes\n";

    public function testTheSampleFileImportsEveryRowWithPhonesNormalised(): void
    {
        $this->signInAs('manager');
        $job = $this->importAndRun('customers', $this->sample('customers'));

        self::assertSame('done', $job['status']);
        self::assertSame([3, 3, 0, 0, 0], [$job['rows_total'], $job['rows_created'], $job['rows_updated'], $job['rows_skipped'], $job['rows_failed']], 'The sample file we ship must import cleanly.');
        self::assertNotNull($job['finished_at']);
        $maria = $this->customerIdByPhone('+18135557101');
        self::assertNotFalse($maria, '"(813) 555-7101" is stored as E.164.');
        self::assertNotFalse($this->customerIdByPhone('+18135557102'), '"813.555.7102" is stored as E.164.');
        $customer = $this->db()->fetchAssociative('SELECT * FROM customers WHERE id = ?', [$maria]);
        self::assertIsArray($customer);
        self::assertSame(['Maria', 'Gonzalez', 'import', $this->dealershipId()], [$customer['first_name'], $customer['last_name'], $customer['source'], $customer['dealership_id']]);
        self::assertSame($this->userId('sales'), $customer['sales1_user_id'], 'sales_rep_email names the Sales 1.');
        $note = (string) $this->db()->fetchOne('SELECT body FROM customer_notes WHERE customer_id = ?', [$maria]);
        self::assertStringContainsString('Prefers texts after 5 pm', $note);
        self::assertStringContainsString('1200 Bayshore Blvd', $note, 'There is no address field: the address is kept in the note.');
        self::assertSame($this->userId('manager'), $this->db()->fetchOne('SELECT author_user_id FROM customer_notes WHERE customer_id = ?', [$maria]), 'Notes are written by the uploader.');
    }

    public function testConsentIsRecordedWithSourceImportWhenTheColumnSaysYes(): void
    {
        $job = $this->importAndRun('customers', self::HEADER
            ."Lia,Moreno,813-555-7301,lia@example.com,,,yes,no,Showroom form 2024,\n"
            ."Ben,Ito,813-555-7302,ben@example.com,,,no,YES,,\n");

        self::assertSame(2, $job['rows_created']);
        $lia = $this->customerIdByPhone('+18135557301');
        $consents = $this->db()->fetchAllAssociative('SELECT * FROM consents WHERE customer_id = ?', [$lia]);
        self::assertCount(1, $consents, 'sms_consent yes records text consent; email_consent no records nothing.');
        self::assertSame(['text', 'granted', 'import', '+18135557301'], [$consents[0]['channel'], $consents[0]['state'], $consents[0]['source'], $consents[0]['contact_value']]);
        self::assertSame('Imported from the dealer\'s previous CRM', $consents[0]['wording']);
        self::assertSame('Showroom form 2024', $consents[0]['captured_where'], 'consent_source says where the old CRM got it.');
        self::assertSame($this->userId('manager'), $consents[0]['captured_by_user_id']);
        $ben = $this->customerIdByPhone('+18135557302');
        self::assertSame(['email' => 'ben@example.com'], $this->db()->fetchAllKeyValue('SELECT channel, contact_value FROM consents WHERE customer_id = ?', [$ben]), '"YES" counts, on the email.');
    }

    public function testAnImportNeverOverridesARevokedConsent(): void
    {
        $revoked = (int) $this->db()->fetchOne("SELECT COUNT(*) FROM consents WHERE contact_value = '+18135551025'");
        $job = $this->importAndRun('customers', self::HEADER.",,813-555-1025,,,,yes,,,\n");

        self::assertSame('done', $job['status']);
        self::assertSame($revoked, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM consents WHERE contact_value = '+18135551025'"), 'Customer 25 texted STOP: an old spreadsheet cannot opt them back in.');
    }

    public function testDuplicatesAreUpdatedNotDuplicatedAndASecondRunChangesNothing(): void
    {
        $seeded = $this->customerId(3);
        $customers = (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customers');
        $file = self::HEADER
            ."Bobby,Renamed,(813) 555-1003,bobby.new@example.com,,sales2@mustang.test,,,,\n"
            ."Zoe,Park,813-555-7401,,,,,,,First row\n"
            ."Zoe,Park,813-555-7401,zoe@example.com,,,,,,\n"
            ."Zoe,Park,,zoe@example.com,,,,,,\n";

        $first = $this->importAndRun('customers', $file);
        self::assertSame([4, 1, 2, 1, 0], [$first['rows_total'], $first['rows_created'], $first['rows_updated'], $first['rows_skipped'], $first['rows_failed']], json_encode($first['errors']) ?: '');
        self::assertSame($customers + 1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customers'), 'A seeded customer matched by phone is not created again.');
        self::assertSame($seeded, $this->customerIdByEmail('bobby.new@example.com'), 'The new email is added to the matched customer.');
        self::assertSame(['Bobby', 'Renamed', $this->userId('sales2')], array_values((array) $this->db()->fetchAssociative('SELECT first_name, last_name, sales1_user_id FROM customers WHERE id = ?', [$seeded])));
        $zoe = $this->customerIdByPhone('+18135557401');
        self::assertSame($zoe, $this->customerIdByEmail('zoe@example.com'), 'Rows of one file that share a contact are one customer.');

        $second = $this->importAndRun('customers', $file);
        self::assertSame([4, 0, 0, 4, 0], [$second['rows_total'], $second['rows_created'], $second['rows_updated'], $second['rows_skipped'], $second['rows_failed']], 'The same file twice: every row is a duplicate with nothing new.');
        self::assertSame($customers + 1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customers'));
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customer_notes WHERE customer_id = ?', [$zoe]), 'The same note is not added twice.');
    }

    public function testBadRowsAreReportedInErrorsAndTheRestStillLands(): void
    {
        $job = $this->importAndRun('customers', self::HEADER
            ."Good,One,813-555-7501,,,,,,,\n"
            .",Nameless,813-555-7502,,,,,,,\n"
            ."Bad,Phone,12345,,,,,,,\n"
            ."No,Contact,,,,,,,,\n"
            ."Bad,Email,,not-an-email,,,,,,\n"
            ."Bad,Consent,813-555-7503,,,,maybe,,,\n"
            ."Two,Owners,813-555-1001,,,,,,,\n"
            ."\n"
            ."Good,Two,,good.two@example.com,,nobody@mustang.test,,yes,,\n");

        self::assertSame('done', $job['status']);
        self::assertSame([8, 2, 1, 5], [$job['rows_total'], $job['rows_created'], $job['rows_updated'], $job['rows_failed']], json_encode($job['errors']) ?: '');
        self::assertSame([3, 4, 5, 6, 7], array_column($job['errors'], 'row'), 'Rows are numbered as in the file (the header is row 1); the blank line is not counted.');
        self::assertSame(['first_name', 'phone', 'phone', 'email', 'sms_consent'], array_column($job['errors'], 'field'));
        self::assertFalse($this->customerIdByPhone('+18135557502'), 'A failed row creates nothing.');
        self::assertFalse($this->customerIdByPhone('+18135557503'));
        self::assertNull($this->db()->fetchOne('SELECT sales1_user_id FROM customers WHERE id = ?', [$this->customerIdByEmail('good.two@example.com')]), 'A rep email nobody has leaves the customer unassigned.');
        self::assertSame(['Two', 'Owners'], array_values((array) $this->db()->fetchAssociative('SELECT first_name, last_name FROM customers WHERE id = ?', [$this->customerId(1)])), 'Row 8 matched customer 1 by phone and updated it.');
    }

    public function testAContactThatBelongsToAnotherCustomerFailsTheRow(): void
    {
        $job = $this->importAndRun('customers', self::HEADER."Mixed,Up,813-555-1001,".$this->email(2).",,,,,,\n");

        self::assertSame(1, $job['rows_failed']);
        self::assertSame('email', $job['errors'][0]['field'], 'The phone is customer 1\'s and the email customer 2\'s: neither is changed.');
    }

    public function testAFileWithoutTheRequiredColumnsFailsWithAReason(): void
    {
        $job = $this->importAndRun('customers', "first_name,phone\nAna,8135557601\n");

        self::assertSame('failed', $job['status']);
        self::assertStringContainsString('last_name', $job['errors'][0]['message'], 'The message names the missing columns.');
        self::assertFalse($this->customerIdByPhone('+18135557601'));
    }

    public function testAnEmptyFileFailsAndExcelsBomAndCrLfAreFine(): void
    {
        $empty = $this->importAndRun('customers', '');
        $bom = $this->importAndRun('customers', "\xEF\xBB\xBF".str_replace("\n", "\r\n", self::HEADER."Bom,Crlf,813-555-7602,,,,,,,\n"));

        self::assertSame('failed', $empty['status']);
        self::assertSame('done', $bom['status']);
        self::assertNotFalse($this->customerIdByPhone('+18135557602'));
    }

    private function email(int $n): string
    {
        return (string) $this->db()->fetchOne("SELECT value FROM customer_contacts WHERE kind = 'email' AND customer_id = ?", [$this->customerId($n)]);
    }
}
