<?php

namespace App\Tests\Functional\Compliance;

final class ConsentApiTest extends ComplianceTestCase
{
    public function testAnotherDealershipCannotReadRecordOrRevoke(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $consent = $this->record($customer, 'text', '+18135551003');

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/customers/'.$customer.'/consents');
        $this->assertNotFoundForOtherDealership('POST', '/api/v1/customers/'.$customer.'/consents', ['channel' => 'text', 'contact_value' => '+18135551003', 'source' => 'verbal']);
        $this->assertNotFoundForOtherDealership('POST', '/api/v1/consents/'.$consent['id'].'/revoke', []);

        $this->signInAs('sales');
        self::assertSame('granted', $this->ledger($customer)['summary']['text'], 'The other dealership changed nothing.');
        self::assertCount(1, $this->ledger($customer)['items']);
    }

    public function testEveryRoleMayRecordConsent(): void
    {
        $customer = $this->customerId(3);
        foreach (['sales', 'bdc', 'manager', 'admin'] as $role) {
            $this->signInAs($role);
            $this->record($customer, 'text', '+18135551003');
            $this->assertStatus(201, $role.' records consent.');
        }
    }

    public function testTheSuppressionListShowsOnlyContactsOfMyDealershipAndSearches(): void
    {
        $this->signInAs('sales');
        $this->db()->executeStatement(
            "INSERT INTO suppressions (id, contact_value, channel, reason, created_at, updated_at) VALUES
             (UUID(), '+18135551004', 'text', 'stop_keyword', NOW(), NOW()),
             (UUID(), '+17275552001', 'text', 'stop_keyword', NOW(), NOW()),
             (UUID(), 'ashley.garcia@example.com', 'email', 'unsubscribe', NOW(), NOW())",
        );

        $page = $this->request('GET', '/api/v1/compliance/suppressions?per_page=100');
        $this->assertStatus(200);
        $values = array_column($page['items'], 'contact_value');
        self::assertContains('+18135551004', $values);
        self::assertContains('ashley.garcia@example.com', $values);
        self::assertNotContains('+17275552001', $values, 'Another dealership\'s customers\' contacts are not listed to us.');
        self::assertSame(\count($values), $page['total']);

        $found = $this->request('GET', '/api/v1/compliance/suppressions?q=813-555-1004');
        self::assertSame(['+18135551004'], array_column($found['items'], 'contact_value'), 'A phone matches however it is typed.');
        $found = $this->request('GET', '/api/v1/compliance/suppressions?q=garcia');
        self::assertSame(['ashley.garcia@example.com'], array_column($found['items'], 'contact_value'));
        $nothing = $this->request('GET', '/api/v1/compliance/suppressions?q=100%25');
        self::assertSame(0, $nothing['total'], 'A % is matched literally.');

        $this->signInAs('other');
        $theirs = array_column($this->request('GET', '/api/v1/compliance/suppressions?per_page=100')['items'], 'contact_value');
        self::assertContains('+17275552001', $theirs);
        self::assertNotContains('+18135551004', $theirs);
    }
}
