<?php

namespace App\Tests\Functional\Compliance;

final class ConsentLedgerTest extends ComplianceTestCase
{
    public function testRecordingKeepsSourceWhoWhereWhenWordingAndCampaign(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);

        $consent = $this->record($customer, 'text', '(813) 555-1003', [
            'source' => 'written',
            'captured_where' => 'Showroom desk',
            'captured_at' => '2026-10-01T14:30:00+00:00',
            'wording' => 'I agree to receive texts from Mustang Motors.',
            'campaign' => 'Fall sales event',
        ]);

        $this->assertStatus(201);
        self::assertSame($customer, $consent['customer_id']);
        self::assertSame('+18135551003', $consent['contact_value'], 'The phone is stored in E.164, however it was typed.');
        self::assertSame('text', $consent['channel']);
        self::assertSame('granted', $consent['state']);
        self::assertSame('written', $consent['source']);
        self::assertSame(['id' => $this->userId('sales'), 'name' => 'Sam Patel'], $consent['captured_by'], 'Who captured it is the signed-in user.');
        self::assertSame('Showroom desk', $consent['captured_where']);
        self::assertSame('I agree to receive texts from Mustang Motors.', $consent['wording']);
        self::assertSame('Fall sales event', $consent['campaign']);
        self::assertSame('2026-10-01T14:30:00+00:00', $consent['captured_at'], 'When it was captured can be given.');
        self::assertNull($consent['revoked_at']);
    }

    public function testWithoutAWhenTheCaptureIsNow(): void
    {
        $this->signInAs('bdc');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);

        $consent = $this->record($customer, 'email', 'Jessica.Brown@Example.com');

        $this->assertStatus(201);
        self::assertSame('jessica.brown@example.com', $consent['contact_value'], 'Emails are stored lowercase.');
        self::assertEqualsWithDelta(time(), (new \DateTimeImmutable($consent['captured_at']))->getTimestamp(), 60);
    }

    public function testTheStateIsKeptPerChannel(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);

        $this->record($customer, 'text', '+18135551003');
        $this->record($customer, 'call', '+18135551003');
        $ledger = $this->ledger($customer);

        $this->assertStatus(200);
        self::assertSame(['text' => 'granted', 'email' => 'missing', 'call' => 'granted'], $ledger['summary'], 'Text consent says nothing about email.');
        self::assertCount(2, $ledger['items']);
        self::assertSame('call', $ledger['items'][0]['channel'], 'Newest record first.');
        $detail = $this->request('GET', '/api/v1/customers/'.$customer);
        self::assertSame($ledger['summary'], $detail['consent'], 'The customer record shows the same state as the ledger.');
    }

    public function testRevokingEndsTheConsentAndIsKeptOnTheRecord(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $consent = $this->record($customer, 'text', '+18135551003');

        $revoked = $this->request('POST', '/api/v1/consents/'.$consent['id'].'/revoke', ['reason' => 'Asked us not to text']);

        $this->assertStatus(200);
        self::assertSame('revoked', $revoked['state']);
        self::assertSame('Asked us not to text', $revoked['revoked_reason']);
        self::assertNotNull($revoked['revoked_at']);
        self::assertSame('revoked', $this->ledger($customer)['summary']['text']);

        $again = $this->request('POST', '/api/v1/consents/'.$consent['id'].'/revoke', []);
        $this->assertError(409, 'consent_already_revoked', $again, 'A revoked consent cannot be revoked again.');
    }

    public function testRevokingOneRecordRevokesEveryGrantForThatContactAndChannel(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $older = $this->record($customer, 'text', '+18135551003', ['captured_at' => '2026-09-01T10:00:00+00:00']);
        $this->record($customer, 'text', '+18135551003');
        $this->record($customer, 'email', 'jessica.brown@example.com');

        $this->request('POST', '/api/v1/consents/'.$older['id'].'/revoke', []);

        $this->assertStatus(200);
        self::assertSame(['text' => 'revoked', 'email' => 'granted', 'call' => 'missing'], $this->ledger($customer)['summary'], 'A newer grant must not survive the revocation.');
    }

    public function testConsentRecordedAfterARevocationGrantsAgain(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $first = $this->record($customer, 'text', '+18135551003');
        $this->request('POST', '/api/v1/consents/'.$first['id'].'/revoke', []);

        $this->record($customer, 'text', '+18135551003', ['source' => 'website']);

        self::assertSame('granted', $this->ledger($customer)['summary']['text']);
        self::assertCount(2, $this->ledger($customer)['items'], 'The ledger keeps the revoked record too.');
    }

    public function testTheContactMustBeTheCustomersAndFitTheChannel(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);

        $this->assertError(422, 'contact_not_on_customer', $this->record($customer, 'text', '+18135559999'), 'Consent is for one of the customer\'s own contacts.');
        $this->assertError(422, 'contact_not_on_customer', $this->record($customer, 'email', 'someone@example.com'));
        $this->assertError(422, 'invalid_phone', $this->record($customer, 'text', 'jessica.brown@example.com'), 'Text consent is for a phone number.');
        $this->assertError(422, 'invalid_email', $this->record($customer, 'email', '+18135551003'), 'Email consent is for an email address.');
    }

    public function testRequiredFieldsAndDatesAreChecked(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);

        $body = $this->request('POST', '/api/v1/customers/'.$customer.'/consents', ['channel' => 'text']);
        $this->assertError(422, 'validation', $body);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('contact_value', $fields);
        self::assertContains('source', $fields);

        $this->assertError(422, 'invalid_captured_at', $this->record($customer, 'text', '+18135551003', ['captured_at' => 'last tuesday']));
        $this->assertError(422, 'captured_at_in_future', $this->record($customer, 'text', '+18135551003', ['captured_at' => '2099-01-01T00:00:00+00:00']), 'Consent cannot be captured in the future.');
    }

    public function testEverySourceIsAccepted(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);

        foreach (['verbal', 'written', 'website', 'sms_keyword', 'import'] as $source) {
            $this->record($customer, 'text', '+18135551003', ['source' => $source]);
            $this->assertStatus(201, $source.' is a source.');
        }
    }

    public function testAnUnknownCustomerIsNotFound(): void
    {
        $this->signInAs('sales');

        $this->assertError(404, 'customer_not_found', $this->record('01a150c9-0000-7000-8000-000000000999', 'text', '+18135551003'));
        $this->assertError(404, 'customer_not_found', $this->ledger('not-a-uuid'));
        $this->assertError(404, 'consent_not_found', $this->request('POST', '/api/v1/consents/01a150c9-0000-7000-8000-000000000999/revoke', []));
    }
}
