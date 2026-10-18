<?php

namespace App\Tests\Functional\Audit;

/**
 * GET /api/v1/audit: the admin's view of changes and record access, newest first, filtered, one dealership only.
 */
final class AuditApiTest extends AuditTestCase
{
    /**
     * A read by sales and a change by bdc on two customers, so filters have something to tell apart.
     *
     * @return array{viewed: string, changed: string}
     */
    private function activity(): array
    {
        $viewed = $this->customerId(13);
        $changed = $this->customerId(14);
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/customers/'.$viewed);
        $this->assertStatus(200);
        $this->signInAs('bdc');
        $this->request('PATCH', '/api/v1/customers/'.$changed, ['last_name' => 'Audited']);
        $this->assertStatus(200);

        return ['viewed' => $viewed, 'changed' => $changed];
    }

    public function testOnlyAnAdminReadsTheLog(): void
    {
        foreach (['manager', 'sales', 'bdc'] as $role) {
            $this->signInAs($role);
            $body = $this->request('GET', '/api/v1/audit');
            $this->assertError(403, 'forbidden', $body, $role.' must not read the audit log.');
        }
    }

    public function testAnAdminSeesEntriesNewestFirstWithTheActorAndTheDiff(): void
    {
        $ids = $this->activity();
        $this->signInAs('admin');

        $body = $this->request('GET', '/api/v1/audit');
        $this->assertStatus(200);

        self::assertSame(1, $body['page']);
        self::assertSame(25, $body['per_page']);
        self::assertGreaterThanOrEqual(2, $body['total']);
        $items = $body['items'];
        self::assertIsArray($items);
        $first = $items[0];
        self::assertSame('update', $first['action'], 'The change came last, so it is first.');
        self::assertSame('Customer', $first['entity_type']);
        self::assertSame($ids['changed'], $first['entity_id']);
        self::assertSame($ids['changed'], $first['customer_id']);
        self::assertSame(['id' => $this->userId('bdc'), 'name' => 'Brianna Diaz'], $first['actor']);
        self::assertSame('Audited', $first['changes']['last_name']['new'] ?? null);
        self::assertArrayHasKey('ip', $first);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $first['occurred_at']);
        $read = $items[1];
        self::assertSame('read', $read['action']);
        self::assertSame($ids['viewed'], $read['entity_id']);
        self::assertNull($read['changes']);
    }

    public function testFiltersByEntityUserAndPeriod(): void
    {
        $ids = $this->activity();
        $this->signInAs('admin');

        $byEntity = $this->request('GET', '/api/v1/audit?entity_type=Customer&entity_id='.$ids['viewed']);
        $this->assertStatus(200);
        self::assertSame(['read'], array_column($byEntity['items'], 'action'), 'Only the viewed customer\'s entries.');

        $byUser = $this->request('GET', '/api/v1/audit?user_id='.$this->userId('bdc'));
        self::assertSame([$ids['changed']], array_values(array_unique(array_column($byUser['items'], 'entity_id'))), 'Only what bdc did.');

        $byType = $this->request('GET', '/api/v1/audit?entity_type=CustomerContact');
        self::assertSame(0, $byType['total'], 'Nothing was done to a contact.');

        $today = new \DateTimeImmutable('now', new \DateTimeZone('America/New_York'));
        $future = $this->request('GET', '/api/v1/audit?from='.$today->modify('+2 days')->format('Y-m-d'));
        self::assertSame(0, $future['total'], 'Nothing happened after the period starts.');
        $past = $this->request('GET', '/api/v1/audit?to='.$today->modify('-2 days')->format('Y-m-d'));
        self::assertSame(0, $past['total'], 'Nothing happened before the period ends.');
        $now = $this->request('GET', '/api/v1/audit?from='.$today->modify('-1 day')->format('Y-m-d').'&to='.$today->modify('+1 day')->format('Y-m-d'));
        self::assertGreaterThanOrEqual(2, $now['total']);
    }

    public function testAPeriodThatIsNotADateIsRefused(): void
    {
        $this->signInAs('admin');
        $body = $this->request('GET', '/api/v1/audit?from=yesterday-ish');
        $this->assertError(422, 'invalid_period', $body);
    }

    public function testPagesThroughTheLog(): void
    {
        $this->activity();
        $this->signInAs('admin');

        $body = $this->request('GET', '/api/v1/audit?per_page=1&page=2');
        $this->assertStatus(200);
        self::assertCount(1, $body['items']);
        self::assertSame(2, $body['page']);
        self::assertSame('read', $body['items'][0]['action']);
    }

    public function testAnotherDealershipSeesNothingOfThisOne(): void
    {
        $ids = $this->activity();
        $this->signInAs('other');

        $body = $this->request('GET', '/api/v1/audit');
        $this->assertStatus(200);
        self::assertNotContains($ids['viewed'], array_column($body['items'], 'entity_id'));
        self::assertNotContains($ids['changed'], array_column($body['items'], 'entity_id'));

        $byId = $this->request('GET', '/api/v1/audit?entity_id='.$ids['changed']);
        self::assertSame(0, $byId['total'], 'Even by its id, another dealership\'s history is not there.');
    }
}
