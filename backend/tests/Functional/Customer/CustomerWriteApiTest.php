<?php

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class CustomerWriteApiTest extends ApiTestCase
{
    // ---- create ---------------------------------------------------------------------------------------------

    public function testCreateNormalisesThePhoneAndTheEmail(): void
    {
        $this->signInAs('bdc');
        $body = $this->request('POST', '/api/v1/customers', [
            'first_name' => 'Mia',
            'last_name' => 'Okafor',
            'phone' => '(813) 555-7777',
            'email' => 'Mia.Okafor@Example.COM',
            'preferred_channel' => 'email',
            'sales1_user_id' => $this->userId('sales'),
        ]);

        $this->assertStatus(201);
        self::assertSame('Mia Okafor', $body['name']);
        self::assertSame('manual', $body['source'], 'A customer typed in by hand has the source manual.');
        self::assertSame('email', $body['preferred_channel']);
        self::assertSame(['+18135557777', 'mia.okafor@example.com'], array_column($body['contacts'], 'value'), 'Phone to E.164, email to lowercase.');
        self::assertSame([true, true], array_column($body['contacts'], 'is_primary'), 'The first contact of each kind is the primary one.');
        self::assertSame($this->userId('sales'), $body['assignments']['sales1']['id']);
        self::assertNull($body['archived_at']);
    }

    public function testCreateWithAPhoneAloneIsEnough(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/customers', ['first_name' => 'Noah', 'last_name' => 'Reed', 'phone' => '+1 727 555 0101']);

        $this->assertStatus(201);
        self::assertCount(1, $body['contacts']);
        self::assertSame('+17275550101', $body['contacts'][0]['value']);
    }

    public function testCreateNeedsAPhoneOrAnEmail(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/customers', ['first_name' => 'Noah', 'last_name' => 'Reed']);

        $this->assertError(422, 'validation', $body, 'A customer nobody can reach is not worth a record.');
        self::assertSame('phone', $body['violations'][0]['field']);
        self::assertNotSame('', $body['violations'][0]['message']);
    }

    public function testCreateNeedsANameAndAValidEmail(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/customers', ['first_name' => '', 'email' => 'not-an-email']);

        $this->assertError(422, 'validation', $body);
        $fields = array_column($body['violations'], 'field');
        self::assertContains('first_name', $fields);
        self::assertContains('last_name', $fields);
        self::assertContains('email', $fields);
    }

    public function testCreateRefusesAPhoneThatIsNotAPhone(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/customers', ['first_name' => 'No', 'last_name' => 'Phone', 'phone' => '12']);

        $this->assertError(422, 'invalid_phone', $body);
    }

    public function testCreateRefusesAContactAnotherCustomerAlreadyHas(): void
    {
        $this->signInAs('sales');
        $existing = $this->customerId(1);
        $body = $this->request('POST', '/api/v1/customers', ['first_name' => 'Dup', 'last_name' => 'Licate', 'phone' => '813-555-1001']);

        $this->assertError(409, 'duplicate_contact', $body, 'The same phone written differently is the same phone.');
        self::assertSame($existing, $body['detail']['customer_id'], 'The refusal names the record to open instead.');
        self::assertSame(0, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM customers WHERE last_name = 'Licate'"), 'A refused create stores nothing.');
    }

    public function testTheSameNumberInAnotherDealershipIsNotADuplicate(): void
    {
        $this->signInAs('other');
        $this->request('POST', '/api/v1/customers', ['first_name' => 'Same', 'last_name' => 'Number', 'phone' => '+18135551001']);

        $this->assertStatus(201, 'Dealerships do not see each other, so they cannot collide.');
    }

    public function testCreateRefusesAssigneesOfTheWrongRole(): void
    {
        $this->signInAs('manager');
        $body = $this->request('POST', '/api/v1/customers', [
            'first_name' => 'Ann', 'last_name' => 'Wrong', 'phone' => '+17275550102', 'sales1_user_id' => $this->userId('bdc'),
        ]);

        $this->assertError(422, 'assignee_wrong_role', $body, 'A BDC agent cannot be Sales 1.');
    }

    // ---- update ---------------------------------------------------------------------------------------------

    public function testUpdateChangesTheNameAndThePreferredChannel(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $body = $this->request('PATCH', '/api/v1/customers/'.$id, ['first_name' => 'Sara', 'preferred_channel' => 'call']);

        $this->assertStatus(200);
        self::assertSame('Sara Johnson', $body['name'], 'Only the sent fields change.');
        self::assertSame('call', $body['preferred_channel']);
        self::assertSame('Sara', $this->db()->fetchOne('SELECT first_name FROM customers WHERE id = ?', [$id]));
    }

    public function testUpdateRefusesABlankName(): void
    {
        $this->signInAs('sales');
        $body = $this->request('PATCH', '/api/v1/customers/'.$this->customerId(1), ['last_name' => '   ']);

        $this->assertError(422, 'invalid_name', $body);
    }

    public function testEveryWriteOfAnotherDealershipsCustomerIsNotFound(): void
    {
        $id = $this->customerId(1);
        $contact = (string) $this->db()->fetchOne('SELECT id FROM customer_contacts WHERE customer_id = ? LIMIT 1', [$id]);
        $calls = [
            ['PATCH', '/api/v1/customers/'.$id, ['first_name' => 'X']],
            ['POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'phone', 'value' => '+17275550199']],
            ['PATCH', '/api/v1/customers/'.$id.'/contacts/'.$contact, ['label' => 'x']],
            ['DELETE', '/api/v1/customers/'.$id.'/contacts/'.$contact, null],
            ['POST', '/api/v1/customers/'.$id.'/vehicles-owned', ['make' => 'Ford']],
            ['DELETE', '/api/v1/customers/'.$id.'/vehicles-owned/'.$contact, null],
            ['PATCH', '/api/v1/customers/'.$id.'/assignments', ['sales1_user_id' => null]],
            ['GET', '/api/v1/customers/'.$id.'/notes', null],
            ['POST', '/api/v1/customers/'.$id.'/notes', ['body' => 'hi']],
            ['POST', '/api/v1/customers/'.$id.'/archive', null],
        ];
        foreach ($calls as [$method, $path, $payload]) {
            $this->assertNotFoundForOtherDealership($method, $path, $payload);
        }
        self::assertSame('Sarah', $this->db()->fetchOne('SELECT first_name FROM customers WHERE id = ?', [$id]), 'Nothing changed.');
    }

    // ---- contacts -------------------------------------------------------------------------------------------

    public function testAddingAContactNormalisesItAndCanTakeOverAsPrimary(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $body = $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'phone', 'value' => '727 555 0188', 'label' => 'work', 'is_primary' => true]);

        $this->assertStatus(201);
        $phones = array_values(array_filter($body['contacts'], static fn (array $c): bool => 'phone' === $c['kind']));
        self::assertCount(2, $phones);
        $new = array_values(array_filter($phones, static fn (array $c): bool => '+17275550188' === $c['value']));
        self::assertTrue($new[0]['is_primary']);
        self::assertSame('work', $new[0]['label']);
        self::assertCount(1, array_filter($phones, static fn (array $c): bool => $c['is_primary']), 'There is one primary phone, never two.');
    }

    public function testAddingAContactTheCustomerOrAnotherCustomerHasIsRefused(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);

        $own = $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'phone', 'value' => '+18135551001']);
        $this->assertError(409, 'duplicate_contact', $own);

        $other = $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'email', 'value' => 'ASHLEY.Garcia@example.com']);
        $this->assertError(409, 'duplicate_contact', $other, 'Another customer of the dealership already has this email.');
    }

    public function testAnInvalidContactValueIsRefusedByItsKind(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);

        $this->assertError(422, 'invalid_email', $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'email', 'value' => 'nope']));
        $this->assertError(422, 'invalid_phone', $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'phone', 'value' => 'abc']));
        $this->assertError(422, 'validation', $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'fax', 'value' => '1']));
    }

    public function testUpdatingAContactChangesItsValueLabelAndPrimary(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $added = $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'email', 'value' => 'second@example.com']);
        $second = array_values(array_filter($added['contacts'], static fn (array $c): bool => 'second@example.com' === $c['value']))[0];
        self::assertFalse($second['is_primary'], 'A second email is not primary until chosen.');

        $body = $this->request('PATCH', '/api/v1/customers/'.$id.'/contacts/'.$second['id'], ['value' => 'Renamed@Example.com', 'label' => 'home', 'is_primary' => true]);

        $this->assertStatus(200);
        $emails = array_values(array_filter($body['contacts'], static fn (array $c): bool => 'email' === $c['kind']));
        $renamed = array_values(array_filter($emails, static fn (array $c): bool => $c['id'] === $second['id']))[0];
        self::assertSame('renamed@example.com', $renamed['value']);
        self::assertSame('home', $renamed['label']);
        self::assertTrue($renamed['is_primary']);
        self::assertCount(1, array_filter($emails, static fn (array $c): bool => $c['is_primary']));
    }

    public function testUpdatingAContactToAValueSomeoneElseHasIsRefused(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $contact = (string) $this->db()->fetchOne("SELECT id FROM customer_contacts WHERE customer_id = ? AND kind = 'phone'", [$id]);

        $body = $this->request('PATCH', '/api/v1/customers/'.$id.'/contacts/'.$contact, ['value' => '+18135551002']);

        $this->assertError(409, 'duplicate_contact', $body);
        self::assertSame('+18135551001', $this->db()->fetchOne('SELECT value FROM customer_contacts WHERE id = ?', [$contact]));
    }

    public function testKeepingTheSameValueIsNotADuplicate(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $contact = (string) $this->db()->fetchOne("SELECT id FROM customer_contacts WHERE customer_id = ? AND kind = 'phone'", [$id]);

        $this->request('PATCH', '/api/v1/customers/'.$id.'/contacts/'.$contact, ['value' => '(813) 555-1001', 'label' => 'cell']);

        $this->assertStatus(200, 'Saving a contact with its own number must not collide with itself.');
    }

    public function testRemovingThePrimaryPromotesTheNextOfTheSameKind(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'phone', 'value' => '+17275550177']);
        $primary = (string) $this->db()->fetchOne("SELECT id FROM customer_contacts WHERE customer_id = ? AND kind = 'phone' AND is_primary = 1", [$id]);

        $body = $this->request('DELETE', '/api/v1/customers/'.$id.'/contacts/'.$primary);

        $this->assertStatus(200);
        $phones = array_values(array_filter($body['contacts'], static fn (array $c): bool => 'phone' === $c['kind']));
        self::assertCount(1, $phones);
        self::assertTrue($phones[0]['is_primary'], 'The remaining phone becomes the primary one.');
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customer_contacts WHERE id = ?', [$primary]));
    }

    public function testAContactOfAnotherCustomerIsNotFoundThroughThisOne(): void
    {
        $this->signInAs('sales');
        $theirs = (string) $this->db()->fetchOne('SELECT id FROM customer_contacts WHERE customer_id = ? LIMIT 1', [$this->customerId(2)]);

        $this->request('DELETE', '/api/v1/customers/'.$this->customerId(1).'/contacts/'.$theirs);
        $this->assertStatus(404);
        $this->request('PATCH', '/api/v1/customers/'.$this->customerId(1).'/contacts/'.$theirs, ['label' => 'x']);
        $this->assertStatus(404);
    }

    // ---- owned vehicles -------------------------------------------------------------------------------------

    public function testAnOwnedVehicleIsAddedAndRemoved(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $body = $this->request('POST', '/api/v1/customers/'.$id.'/vehicles-owned', [
            'year' => 2018, 'make' => 'Toyota', 'model' => 'Camry', 'trim' => 'SE', 'vin' => '4T1B11HK5JU000001', 'purchased_at' => '2019-04-12',
        ]);

        $this->assertStatus(201);
        self::assertCount(1, $body['owned_vehicles']);
        $vehicle = $body['owned_vehicles'][0];
        self::assertSame('Camry', $vehicle['model']);
        self::assertStringStartsWith('2019-04-12', $vehicle['purchased_at']);

        $after = $this->request('DELETE', '/api/v1/customers/'.$id.'/vehicles-owned/'.$vehicle['id']);
        $this->assertStatus(200);
        self::assertSame([], $after['owned_vehicles']);

        $this->request('DELETE', '/api/v1/customers/'.$id.'/vehicles-owned/'.$vehicle['id']);
        $this->assertStatus(404, 'Removing it twice is not found.');
    }

    public function testAnOwnedVehicleNeedsSomethingToDescribeIt(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);

        $this->assertError(422, 'vehicle_empty', $this->request('POST', '/api/v1/customers/'.$id.'/vehicles-owned', []));
        $this->assertError(422, 'invalid_date', $this->request('POST', '/api/v1/customers/'.$id.'/vehicles-owned', ['make' => 'Ford', 'purchased_at' => 'last spring']));
        $this->assertError(422, 'validation', $this->request('POST', '/api/v1/customers/'.$id.'/vehicles-owned', ['year' => 1800]));
    }

    // ---- assignments ----------------------------------------------------------------------------------------

    public function testTheTeamIsAssignedByRole(): void
    {
        $this->signInAs('manager');
        $id = $this->customerId(1);
        $body = $this->request('PATCH', '/api/v1/customers/'.$id.'/assignments', [
            'sales1_user_id' => $this->userId('sales2'),
            'sales2_user_id' => $this->userId('sales'),
            'bdc_user_id' => $this->userId('bdc'),
        ]);

        $this->assertStatus(200);
        self::assertSame($this->userId('sales2'), $body['assignments']['sales1']['id']);
        self::assertSame($this->userId('sales'), $body['assignments']['sales2']['id']);
        self::assertSame('bdc', strtolower((string) $this->db()->fetchOne('SELECT role FROM users WHERE id = ?', [$body['assignments']['bdc']['id']])));
        self::assertNotSame('', $body['assignments']['bdc']['name']);
    }

    public function testSendingNullClearsASlot(): void
    {
        $this->signInAs('manager');
        $id = $this->customerId(3);
        $body = $this->request('PATCH', '/api/v1/customers/'.$id.'/assignments', ['sales1_user_id' => $this->userId('sales')]);

        $this->assertStatus(200);
        self::assertNull($body['assignments']['bdc'], 'The three slots are set together: one left out is empty.');
        self::assertNull($body['assignments']['sales2']);
    }

    public function testAnAssigneeMustHaveTheRightRole(): void
    {
        $this->signInAs('manager');
        $id = $this->customerId(1);

        $bdcAsSales = $this->request('PATCH', '/api/v1/customers/'.$id.'/assignments', ['sales1_user_id' => $this->userId('bdc')]);
        $this->assertError(422, 'assignee_wrong_role', $bdcAsSales);
        self::assertSame('sales1', $bdcAsSales['detail']['slot']);

        $salesAsBdc = $this->request('PATCH', '/api/v1/customers/'.$id.'/assignments', ['bdc_user_id' => $this->userId('sales')]);
        $this->assertError(422, 'assignee_wrong_role', $salesAsBdc);
    }

    public function testAnAssigneeFromAnotherDealershipOrUnknownIsRefused(): void
    {
        $this->signInAs('manager');
        $id = $this->customerId(1);
        $other = (string) $this->db()->fetchOne("SELECT id FROM users WHERE email = 'other@mustang.test'");

        $this->assertError(422, 'assignee_not_found', $this->request('PATCH', '/api/v1/customers/'.$id.'/assignments', ['sales1_user_id' => $other]));
        $this->assertError(422, 'assignee_not_found', $this->request('PATCH', '/api/v1/customers/'.$id.'/assignments', ['sales1_user_id' => '01a150c9-0000-7000-8000-000000000000']));
    }

    public function testADeactivatedUserCannotBeAssigned(): void
    {
        $this->signInAs('manager');
        $this->db()->executeStatement('UPDATE users SET active = 0 WHERE id = ?', [$this->userId('sales2')]);

        $body = $this->request('PATCH', '/api/v1/customers/'.$this->customerId(2).'/assignments', ['sales1_user_id' => $this->userId('sales2')]);

        $this->assertError(422, 'assignee_not_found', $body, 'Only active users take customers.');
    }

    // ---- notes ----------------------------------------------------------------------------------------------

    public function testANoteIsAddedWithItsAuthorAndListedNewestFirst(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(18);
        $first = $this->request('POST', '/api/v1/customers/'.$id.'/notes', ['body' => 'Wants a third row, budget around 45k.']);
        $this->assertStatus(201);
        self::assertSame('Wants a third row, budget around 45k.', $first['body']);
        self::assertSame($this->userId('sales'), $first['author']['id']);

        $this->signInAs('bdc');
        $this->request('POST', '/api/v1/customers/'.$id.'/notes', ['body' => 'Called back, voicemail.']);
        $this->db()->executeStatement('UPDATE customer_notes SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE id = ?', [$first['id']]);

        $list = $this->request('GET', '/api/v1/customers/'.$id.'/notes');
        $this->assertStatus(200);
        self::assertSame(['Called back, voicemail.', 'Wants a third row, budget around 45k.'], array_column($list, 'body'));
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customer_notes WHERE customer_id = ? AND body = ?', [$id, 'Called back, voicemail.']));
    }

    public function testANoteNeedsText(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/customers/'.$this->customerId(1).'/notes', ['body' => '']);

        $this->assertError(422, 'validation', $body);
        self::assertSame('body', $body['violations'][0]['field']);
        $whitespace = $this->request('POST', '/api/v1/customers/'.$this->customerId(1).'/notes', ['body' => "  \n "]);
        $this->assertError(422, 'invalid_note', $whitespace);
    }

    public function testNotesOfOtherCustomersDoNotLeakIn(): void
    {
        $this->signInAs('sales');
        $this->request('POST', '/api/v1/customers/'.$this->customerId(19).'/notes', ['body' => 'Only on customer 2.']);

        $list = $this->request('GET', '/api/v1/customers/'.$this->customerId(18).'/notes');

        self::assertSame([], $list);
    }

    // ---- archive --------------------------------------------------------------------------------------------

    public function testArchivingHidesTheCustomerFromTheDefaultListAndKeepsTheRecord(): void
    {
        $this->signInAs('manager');
        $id = $this->customerId(4);
        $body = $this->request('POST', '/api/v1/customers/'.$id.'/archive');

        $this->assertStatus(200);
        self::assertNotNull($body['archived_at']);

        $active = $this->request('GET', '/api/v1/customers?per_page=100');
        self::assertSame(29, $active['total']);
        self::assertNotContains($id, array_column($active['items'], 'id'));
        $archived = $this->request('GET', '/api/v1/customers?archived=true');
        self::assertSame([$id], array_column($archived['items'], 'id'));

        $again = $this->request('POST', '/api/v1/customers/'.$id.'/archive');
        $this->assertStatus(200, 'Archiving twice is harmless.');
        self::assertSame($body['archived_at'], $again['archived_at'], 'The first archive date stays.');
    }
}
