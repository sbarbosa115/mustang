<?php

namespace App\Tests\Functional\Lead;

use App\Tests\Support\ApiTestCase;

final class RoutingSettingsTest extends ApiTestCase
{
    public function testBeforeAnyOrderIsSetTheRotationIsTheActiveSalesTeamByName(): void
    {
        $this->signInAs('manager');
        $body = $this->request('GET', '/api/v1/settings/routing');

        $this->assertStatus(200);
        self::assertSame(['Jordan Reyes', 'Sam Patel'], array_column($body['users'], 'name'), 'Only salespeople, by first name.');
        self::assertSame($this->userId('sales2'), $body['next']['id']);
    }

    public function testAManagerSetsTheOrderAndTheNextLeadFollowsIt(): void
    {
        $this->signInAs('manager');
        $body = $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => [$this->userId('sales'), $this->userId('sales2')]]);

        $this->assertStatus(200);
        self::assertSame(['Sam Patel', 'Jordan Reyes'], array_column($body['users'], 'name'));
        self::assertSame('Sam Patel', $body['next']['name'], 'A new order starts from its first rep.');

        $lead = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1010', 'source' => 'phone']);
        self::assertSame($this->userId('sales'), $lead['assignee']['id']);
        $after = $this->request('GET', '/api/v1/settings/routing');
        self::assertSame('Jordan Reyes', $after['next']['name'], 'Each routed lead moves the turn on.');
    }

    public function testAnEmptyOrderLeavesNewLeadsUnassigned(): void
    {
        $this->signInAs('admin');
        $body = $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => []]);
        $this->assertStatus(200, 'An admin is a manager too.');
        self::assertSame([], $body['users']);
        self::assertNull($body['next']);

        $lead = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1011', 'source' => 'phone']);
        $this->assertStatus(201);
        self::assertNull($lead['assignee']);
        self::assertSame('new', $lead['status'], 'Nobody to route to: the lead waits, unassigned.');
    }

    public function testOnlyActiveSalespeopleOfTheDealershipCanBeInTheRotation(): void
    {
        $this->signInAs('manager');
        foreach (['bdc', 'manager', 'other'] as $who) {
            $body = $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => [$this->userId('sales'), $this->userId($who)]]);
            $this->assertError(422, 'routing_user_invalid', $body, $who.' cannot take round-robin leads.');
        }

        $body = $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => [$this->userId('sales'), $this->userId('sales')]]);
        $this->assertError(422, 'routing_duplicate_user', $body);

        $body = $this->request('PUT', '/api/v1/settings/routing', []);
        $this->assertError(422, 'validation', $body);
    }

    public function testADeactivatedRepIsSkippedAndLeftOutOfTheOrder(): void
    {
        $this->signInAs('manager');
        $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => [$this->userId('sales'), $this->userId('sales2')]]);
        $this->db()->executeStatement('UPDATE users SET active = 0 WHERE id = ?', [$this->userId('sales')]);

        $body = $this->request('GET', '/api/v1/settings/routing');
        self::assertSame(['Jordan Reyes'], array_column($body['users'], 'name'));
        $lead = $this->request('POST', '/api/v1/leads', ['phone' => '813-555-1012', 'source' => 'phone']);
        self::assertSame($this->userId('sales2'), $lead['assignee']['id']);
    }

    public function testRoutingIsForManagersOnly(): void
    {
        foreach (['sales', 'bdc'] as $role) {
            $this->signInAs($role);
            $this->request('GET', '/api/v1/settings/routing');
            $this->assertStatus(403, $role.' cannot read the routing.');
            $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => []]);
            $this->assertStatus(403, $role.' cannot change the routing.');
        }
    }

    public function testEachDealershipHasItsOwnRotation(): void
    {
        $this->signInAs('manager');
        $this->request('PUT', '/api/v1/settings/routing', ['user_ids' => [$this->userId('sales')]]);

        $this->signInAs('other');
        $body = $this->request('GET', '/api/v1/settings/routing');
        $this->assertStatus(200);
        self::assertNotContains($this->userId('sales'), array_column($body['users'], 'id'), 'Another rooftop never sees this rotation.');
    }
}
