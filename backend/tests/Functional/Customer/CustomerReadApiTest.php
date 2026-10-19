<?php

namespace App\Tests\Functional\Customer;

use App\Tests\Support\ApiTestCase;

final class CustomerReadApiTest extends ApiTestCase
{
    public function testARepSeesEveryCustomerOfTheirRooftopAndNoneOfAnother(): void
    {
        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/customers?per_page=100');

        $this->assertStatus(200);
        self::assertSame(30, $body['total'], 'Reps see every customer of their dealership; assignment drives lists, not access.');
        $names = array_column($body['items'], 'name');
        self::assertNotContains('Other1 Customer', $names, 'Another dealership\'s customers never appear.');
    }

    public function testAnotherDealershipSeesOnlyItsOwnCustomers(): void
    {
        $this->signInAs('other');
        $body = $this->request('GET', '/api/v1/customers?per_page=100');

        self::assertSame(5, $body['total']);
    }

    public function testAnotherDealershipsCustomerIsNotFound(): void
    {
        $this->assertNotFoundForOtherDealership('GET', '/api/v1/customers/'.$this->customerId(1));
    }

    public function testAnUnknownOrMalformedIdIsNotFound(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/customers/01a150c9-0000-7000-8000-000000000000');
        $this->assertStatus(404);
        $this->request('GET', '/api/v1/customers/not-a-uuid');
        $this->assertStatus(404, 'A malformed id is not found, not a server error.');
    }

    public function testDetailCarriesContactsTeamAndConsentPerChannel(): void
    {
        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/customers/'.$this->customerId(1));

        $this->assertStatus(200);
        self::assertSame('Sarah Johnson', $body['name']);
        self::assertSame('+18135551001', $body['contacts'][0]['value']);
        self::assertNotNull($body['assignments']['sales1']);
        self::assertSame(['text' => 'missing', 'email' => 'missing', 'call' => 'missing'], $body['consent'], 'No consent row means missing.');
        // Customers 11-20 have no seeded deal.
        self::assertNull($this->request('GET', '/api/v1/customers/'.$this->customerId(15))['open_deal']);
    }

    public function testConsentAndSuppressionShowInTheDetail(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(2);
        $dealership = $this->dealershipId();
        $this->db()->executeStatement(
            "INSERT INTO consents (id, dealership_id, customer_id, contact_value, channel, state, source, captured_at, created_at, updated_at)
             VALUES ('01a150c9-0000-7000-8000-00000000c001', ?, ?, '+18135551002', 'text', 'granted', 'test', NOW(), NOW(), NOW())",
            [$dealership, $customer],
        );
        $this->db()->executeStatement(
            "INSERT INTO suppressions (id, contact_value, channel, reason, created_at, updated_at)
             VALUES ('01a150c9-0000-7000-8000-00000000c002', 'michael.williams@example.com', 'email', 'unsubscribe', NOW(), NOW())",
        );

        $consent = $this->request('GET', '/api/v1/customers/'.$customer)['consent'];

        self::assertSame('granted', $consent['text']);
        self::assertSame('opted_out', $consent['email'], 'A suppressed contact overrides any consent for its channel.');
    }

    public function testSearchMatchesNamePhoneDigitsAndEmail(): void
    {
        $this->signInAs('sales');

        self::assertSame(['Sarah Johnson'], array_column($this->request('GET', '/api/v1/customers?q=sarah')['items'], 'name'));
        self::assertSame(['Michael Williams'], array_column($this->request('GET', '/api/v1/customers?q=(813) 555-1002')['items'], 'name'), 'A phone typed with punctuation matches.');
        self::assertSame(['Jessica Brown'], array_column($this->request('GET', '/api/v1/customers?q=jessica.brown@')['items'], 'name'));
    }

    public function testSearchWildcardsAreLiteral(): void
    {
        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/customers?q=%25');

        self::assertSame(0, $body['total'], '"%" matches a literal percent sign, not everything.');
    }

    public function testPaginationIsStableAndBounded(): void
    {
        $this->signInAs('sales');
        $first = $this->request('GET', '/api/v1/customers?per_page=10&page=1');
        $second = $this->request('GET', '/api/v1/customers?per_page=10&page=2');

        self::assertCount(10, $first['items']);
        self::assertSame([], array_intersect(array_column($first['items'], 'id'), array_column($second['items'], 'id')), 'Pages do not overlap.');
        $this->request('GET', '/api/v1/customers?per_page=500');
        $this->assertStatus(422, 'per_page is capped at 100.');
    }

    public function testLookupFindsByNormalisedPhoneOrEmail(): void
    {
        $this->signInAs('bdc');

        self::assertSame(['Sarah Johnson'], array_column($this->request('GET', '/api/v1/customers/lookup?phone=813-555-1001')['matches'], 'name'));
        self::assertSame(['Sarah Johnson'], array_column($this->request('GET', '/api/v1/customers/lookup?email=SARAH.JOHNSON@example.com')['matches'], 'name'));
        self::assertSame([], $this->request('GET', '/api/v1/customers/lookup?phone=12')['matches'], 'A partial phone is no match, not an error.');
    }

    public function testLookupNeverMatchesAnotherDealership(): void
    {
        $this->signInAs('sales');

        self::assertSame([], $this->request('GET', '/api/v1/customers/lookup?phone=+17275552001')['matches']);
    }
}
