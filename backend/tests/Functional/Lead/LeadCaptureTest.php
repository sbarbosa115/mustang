<?php

namespace App\Tests\Functional\Lead;

use App\Lead\Domain\Event\LeadCaptured;
use App\Tests\Support\ApiTestCase;

final class LeadCaptureTest extends ApiTestCase
{
    /**
     * @return list<object> the events published on the event bus during the last request
     */
    private function publishedEvents(): array
    {
        $bus = static::getContainer()->get('event.bus');

        return array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages());
    }

    /**
     * @return list<LeadCaptured>
     */
    private function leadsCaptured(): array
    {
        return array_values(array_filter($this->publishedEvents(), static fn (object $e): bool => $e instanceof LeadCaptured));
    }

    public function testANewCustomerIsCreatedFromTheLead(): void
    {
        $this->signInAs('bdc');
        $body = $this->request('POST', '/api/v1/leads', [
            'first_name' => 'Lena',
            'last_name' => 'Ortiz',
            'phone' => '(813) 555-7001',
            'email' => 'Lena.Ortiz@Example.com',
            'source' => 'phone',
            'channel_note' => 'Called the main line',
            'vehicle_interest_text' => '2025 RAV4 Hybrid',
        ]);

        $this->assertStatus(201);
        self::assertSame('Lena Ortiz', $body['customer']['name']);
        self::assertSame('phone', $body['source']);
        self::assertSame('Called the main line', $body['channel_note']);
        self::assertSame('2025 RAV4 Hybrid', $body['vehicle_interest_text']);
        self::assertNull($body['deal_id'], 'The deal is opened by the Deal context, not here.');
        $contacts = $this->db()->fetchFirstColumn('SELECT value FROM customer_contacts WHERE customer_id = ? ORDER BY kind DESC', [$body['customer']['id']]);
        self::assertSame(['+18135557001', 'lena.ortiz@example.com'], $contacts, 'The customer is created with the normalised contacts.');
        self::assertSame('phone', $this->db()->fetchOne('SELECT source FROM customers WHERE id = ?', [$body['customer']['id']]), 'The new customer carries the lead source.');
    }

    public function testAnExistingCustomerIsMatchedByPhoneWrittenAnotherWay(): void
    {
        $this->signInAs('sales');
        $before = (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customers');
        $body = $this->request('POST', '/api/v1/leads', [
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'phone' => '813.555.1001',
            'source' => 'walk_in',
        ]);

        $this->assertStatus(201);
        self::assertSame($this->customerId(1), $body['customer']['id'], 'Sarah Johnson\'s number, written with dots, is Sarah Johnson.');
        self::assertSame('Sarah Johnson', $body['customer']['name'], 'A matched customer keeps their name.');
        self::assertSame($before, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM customers'), 'No duplicate customer.');
    }

    public function testAnExistingCustomerIsMatchedByEmailWhenThePhoneIsNew(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', [
            'first_name' => 'Michael',
            'last_name' => 'Williams',
            'phone' => '813-555-9999',
            'email' => 'MICHAEL.WILLIAMS@example.com',
            'source' => 'manual',
        ]);

        $this->assertStatus(201);
        self::assertSame($this->customerId(2), $body['customer']['id'], 'No phone match, so the email decides.');
    }

    public function testThePhoneWinsOverTheEmailWhenTheyPointAtDifferentCustomers(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', [
            'phone' => '813-555-1003',
            'email' => 'michael.williams@example.com',
            'source' => 'phone',
        ]);

        $this->assertStatus(201);
        self::assertSame($this->customerId(3), $body['customer']['id'], 'Phone first, then email.');
    }

    public function testAnExplicitCustomerIdIsUsedAsIs(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['customer_id' => $this->customerId(4), 'source' => 'other']);

        $this->assertStatus(201);
        self::assertSame($this->customerId(4), $body['customer']['id']);
    }

    public function testAnotherDealershipsCustomerIdIsRefused(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['customer_id' => $this->otherCustomerId(1), 'source' => 'other']);

        $this->assertError(422, 'customer_not_found', $body, 'Another rooftop\'s customer cannot be attached to a lead.');
    }

    public function testLeadCapturedIsRaisedWithTheCustomerAndTheRep(): void
    {
        $this->signInAs('bdc');
        $body = $this->request('POST', '/api/v1/leads', [
            'phone' => '813-555-1005',
            'source' => 'website',
            'vehicle_interest_text' => 'F-150 Lariat',
            'assigned_user_id' => $this->userId('sales2'),
        ]);

        $this->assertStatus(201);
        $events = $this->leadsCaptured();
        self::assertCount(1, $events, 'One lead, one LeadCaptured: the Deal context opens its deal on it.');
        self::assertSame($body['id'], $events[0]->leadId);
        self::assertSame($this->customerId(5), $events[0]->customerId);
        self::assertSame($this->dealershipId(), $events[0]->dealershipId);
        self::assertSame('website', $events[0]->source);
        self::assertSame($this->userId('sales2'), $events[0]->assignedUserId);
        self::assertSame('F-150 Lariat', $events[0]->vehicleInterestText);
    }

    public function testAnExplicitAssigneeGetsTheLeadAndTheRotationIsNotUsed(): void
    {
        $this->signInAs('manager');
        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1006', 'source' => 'phone', 'assigned_user_id' => $this->userId('bdc')]);

        $this->assertStatus(201);
        self::assertSame($this->userId('bdc'), $body['assignee']['id']);
        self::assertSame('Brianna Diaz', $body['assignee']['name']);
        self::assertSame('assigned', $body['status']);
        self::assertFalse($this->db()->fetchOne('SELECT id FROM routing_rotations WHERE dealership_id = ?', [$this->dealershipId()]), 'The rotation is created only when it is used.');
    }

    public function testWithoutAnAssigneeTheLeadsGoRoundRobinOverTheSalesTeam(): void
    {
        $this->signInAs('bdc');
        $assignees = [];
        foreach (['813-555-7101', '813-555-7102', '813-555-7103'] as $i => $phone) {
            $body = $this->request('POST', '/api/v1/leads', ['first_name' => 'Rr', 'last_name' => 'Lead'.$i, 'phone' => $phone, 'source' => 'phone']);
            $this->assertStatus(201);
            $assignees[] = $body['assignee']['id'] ?? null;
        }

        // Active sales users by first name: Jordan Reyes (sales2), then Sam Patel (sales).
        self::assertSame([$this->userId('sales2'), $this->userId('sales'), $this->userId('sales2')], $assignees);
        $customer = $this->db()->fetchOne('SELECT sales1_user_id FROM customers WHERE last_name = ?', ['Lead0']);
        self::assertSame($this->userId('sales2'), $customer, 'A customer created from a routed lead gets the rep as Sales 1.');
    }

    public function testTheSourceIsRequired(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['first_name' => 'No', 'last_name' => 'Source', 'phone' => '813-555-7201']);

        $this->assertError(422, 'validation', $body);
        self::assertContains('source', array_column($body['violations'], 'field'));
        self::assertSame([], $this->leadsCaptured());
    }

    public function testAnUnknownSourceIsRefused(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-7202', 'source' => 'carrier_pigeon']);

        self::assertSame(422, $this->client->getResponse()->getStatusCode(), 'Only the listed sources are accepted.');
        self::assertArrayHasKey('error', $body);
    }

    public function testAPhoneOrAnEmailIsRequiredWithoutACustomer(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['first_name' => 'Nobody', 'last_name' => 'Reachable', 'source' => 'walk_in']);

        $this->assertError(422, 'validation', $body);
        self::assertSame('phone', $body['violations'][0]['field']);
    }

    public function testANewCustomerNeedsAName(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-7203', 'source' => 'walk_in']);

        $this->assertError(422, 'customer_name_required', $body, 'Nobody matched, so a customer is created, and it needs a name.');
        self::assertFalse($this->db()->fetchOne("SELECT customer_id FROM customer_contacts WHERE value = '+18135557203'"));
    }

    public function testABadPhoneIsRefused(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['first_name' => 'Bad', 'last_name' => 'Phone', 'phone' => '12', 'source' => 'phone']);

        $this->assertError(422, 'invalid_phone', $body);
    }

    public function testAnAssigneeFromAnotherDealershipIsRefused(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1007', 'source' => 'phone', 'assigned_user_id' => $this->userId('other')]);

        $this->assertError(422, 'assignee_not_found', $body);
    }

    public function testAReceivedAtInThePastIsKeptAndOneInTheFutureIsRefused(): void
    {
        $this->signInAs('sales');
        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1008', 'source' => 'phone', 'received_at' => '2026-01-15T09:30:00-05:00']);
        $this->assertStatus(201);
        self::assertStringStartsWith('2026-01-15T14:30:00', $body['received_at'], 'Stored in UTC.');

        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1008', 'source' => 'phone', 'received_at' => '2099-01-01T00:00:00Z']);
        $this->assertError(422, 'received_in_future', $body);

        $body = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1008', 'source' => 'phone', 'received_at' => 'yesterday-ish']);
        $this->assertError(422, 'validation', $body);
        self::assertSame('received_at', $body['violations'][0]['field']);
    }
}
