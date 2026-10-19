<?php

namespace App\Tests\Functional\Lead;

use App\Lead\Domain\Event\LeadAssigned;
use App\Tests\Support\ApiTestCase;

final class LeadApiTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function capture(string $phone, array $extra = []): array
    {
        $body = $this->request('POST', '/api/v1/leads', ['first_name' => 'Api', 'last_name' => 'Lead', 'phone' => $phone, 'source' => 'phone', ...$extra]);
        $this->assertStatus(201);

        return $body;
    }

    public function testOneLeadIsReadBack(): void
    {
        $this->signInAs('sales');
        $lead = $this->capture('813-555-7301', ['vehicle_interest_text' => 'Tacoma']);

        $body = $this->request('GET', '/api/v1/leads/'.$lead['id']);
        $this->assertStatus(200);
        self::assertSame($lead['id'], $body['id']);
        self::assertSame('Tacoma', $body['vehicle_interest_text']);
        self::assertSame('Api Lead', $body['customer']['name']);
    }

    public function testTheListIsNewestFirstAndFilters(): void
    {
        $this->signInAs('manager');
        $older = $this->capture('813-555-7302', ['received_at' => '2026-02-01T10:00:00Z', 'assigned_user_id' => $this->userId('sales')]);
        $newer = $this->capture('813-555-7303', ['received_at' => '2026-03-01T10:00:00Z', 'source' => 'walk_in', 'assigned_user_id' => $this->userId('sales2')]);

        $body = $this->request('GET', '/api/v1/leads?per_page=100');
        $this->assertStatus(200);
        $ids = array_column($body['items'], 'id');
        self::assertLessThan(array_search($older['id'], $ids, true), array_search($newer['id'], $ids, true), 'Newest received first.');

        $mine = $this->request('GET', '/api/v1/leads?assigned_to='.$this->userId('sales'));
        self::assertContains($older['id'], array_column($mine['items'], 'id'));
        self::assertNotContains($newer['id'], array_column($mine['items'], 'id'));

        $walkIns = $this->request('GET', '/api/v1/leads?source=walk_in');
        self::assertSame(['walk_in'], array_values(array_unique(array_column($walkIns['items'], 'source'))));

        $assigned = $this->request('GET', '/api/v1/leads?status=assigned&per_page=100');
        self::assertContains($newer['id'], array_column($assigned['items'], 'id'));
    }

    public function testTheSearchMatchesTheCustomerAndTreatsWildcardsLiterally(): void
    {
        $this->signInAs('sales');
        $lead = $this->capture('813-555-7304');

        $byPhone = $this->request('GET', '/api/v1/leads?q=555-7304');
        self::assertSame([$lead['id']], array_column($byPhone['items'], 'id'), 'A phone typed with dashes finds the lead.');
        self::assertSame(1, $byPhone['total']);

        $byName = $this->request('GET', '/api/v1/leads?q=Api%20Lead');
        self::assertContains($lead['id'], array_column($byName['items'], 'id'));

        $wild = $this->request('GET', '/api/v1/leads?q=%25');
        self::assertSame(0, $wild['total'], '"%" matches itself, not everything.');
    }

    public function testTheListPaginates(): void
    {
        $this->signInAs('sales');
        $this->capture('813-555-7305');
        $this->capture('813-555-7306');

        $body = $this->request('GET', '/api/v1/leads?per_page=1&page=2');
        $this->assertStatus(200);
        self::assertCount(1, $body['items']);
        self::assertSame(2, $body['page']);
        self::assertSame(1, $body['per_page']);
        self::assertGreaterThanOrEqual(2, $body['total']);
    }

    public function testALeadIsReassignedAndLeadAssignedIsRaised(): void
    {
        $this->signInAs('manager');
        $lead = $this->capture('813-555-7307', ['assigned_user_id' => $this->userId('sales')]);

        $body = $this->request('PATCH', '/api/v1/leads/'.$lead['id'].'/assignee', ['user_id' => $this->userId('sales2')]);
        $this->assertStatus(200);
        self::assertSame('Jordan Reyes', $body['assignee']['name']);
        $bus = static::getContainer()->get('event.bus');
        $assigned = array_values(array_filter(array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages()), static fn (object $e): bool => $e instanceof LeadAssigned));
        self::assertCount(1, $assigned);
        self::assertSame($this->userId('sales2'), $assigned[0]->assignedUserId);

        $body = $this->request('PATCH', '/api/v1/leads/'.$lead['id'].'/assignee', ['user_id' => $this->userId('admin')]);
        $this->assertError(422, 'assignee_wrong_role', $body, 'An admin does not work leads.');

        $body = $this->request('PATCH', '/api/v1/leads/'.$lead['id'].'/assignee', []);
        $this->assertError(422, 'validation', $body);
    }

    public function testALeadIsClosedOnce(): void
    {
        $this->signInAs('sales');
        $lead = $this->capture('813-555-7308');

        $body = $this->request('POST', '/api/v1/leads/'.$lead['id'].'/close', ['reason' => 'Bought elsewhere']);
        $this->assertStatus(200);
        self::assertSame('closed', $body['status']);

        $body = $this->request('POST', '/api/v1/leads/'.$lead['id'].'/close');
        $this->assertError(409, 'lead_closed', $body);
        $body = $this->request('PATCH', '/api/v1/leads/'.$lead['id'].'/assignee', ['user_id' => $this->userId('sales2')]);
        $this->assertError(409, 'lead_closed', $body, 'A closed lead is not handed to anyone.');
    }

    public function testAnUnknownLeadIsNotFound(): void
    {
        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/leads/0199a000-0000-7000-8000-000000000000');
        $this->assertError(404, 'lead_not_found', $body);
        $this->request('GET', '/api/v1/leads/not-a-uuid');
        $this->assertStatus(404);
    }

    public function testAnotherDealershipGets404AndSeesNoRows(): void
    {
        $this->signInAs('sales');
        $lead = $this->capture('813-555-7309');

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/leads/'.$lead['id']);
        $this->assertNotFoundForOtherDealership('PATCH', '/api/v1/leads/'.$lead['id'].'/assignee', ['user_id' => $this->userId('other')]);
        $this->assertNotFoundForOtherDealership('POST', '/api/v1/leads/'.$lead['id'].'/close');

        $this->signInAs('other');
        $list = $this->request('GET', '/api/v1/leads?per_page=100');
        $this->assertStatus(200);
        self::assertNotContains($lead['id'], array_column($list['items'], 'id'));
        self::assertSame([], array_values(array_filter($list['items'], fn (array $l): bool => \in_array($l['customer']['id'], [$this->customerId(1), $lead['customer']['id']], true))));
    }

    public function testSigningInIsRequired(): void
    {
        $this->request('GET', '/api/v1/leads');
        $this->assertStatus(401);
    }
}
