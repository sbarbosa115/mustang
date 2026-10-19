<?php

namespace App\Tests\Functional\Worklist;

final class InterventionApiTest extends WorklistTestCase
{
    public function testOnlyManagersSeeTheQueue(): void
    {
        foreach (['sales', 'bdc'] as $role) {
            $this->signInAs($role);
            $this->request('GET', '/api/v1/worklist/interventions');
            $this->assertStatus(403, $role.' is not a manager.');
        }
        foreach (['manager', 'admin'] as $role) {
            $this->signInAs($role);
            $this->request('GET', '/api/v1/worklist/interventions');
            $this->assertStatus(200, $role.' sees the queue.');
        }
    }

    public function testDealsAtRiskWithTheirReasonAndTheGrossAtStake(): void
    {
        $this->db()->executeStatement('UPDATE dealerships SET average_gross_cents = 250000 WHERE id = ?', [$this->dealershipId()]);
        $unanswered = $this->customer(2);
        $this->conversation($unanswered, [['in', 'text', 'Can I come at 5?', '-90 minutes']]);
        $unansweredDeal = $this->deal($unanswered, 'engaged', '-1 day', 'sales', 450000);
        $idle = $this->customer(3);
        $this->deal($idle, 'engaged', '-7 days', 'sales2');
        $noShow = $this->customer(4);
        $this->deal($noShow, 'appointment', '-3 days');
        $this->appointment($noShow, '-30 hours', 'no_show');
        $lead = $this->customer(5, null);
        $this->lead($lead, '-3 hours', null, 'new');
        $this->deal($lead, 'new', '-3 hours', null);
        $repsOnly = $this->customer(6);
        $this->deal($repsOnly, 'engaged', '-4 days');
        $recent = $this->customer(7, 'sales2');
        $this->conversation($recent, [['in', 'text', 'Hi', '-20 minutes']]);
        $this->deal($recent, 'engaged', '-1 day', 'sales2');
        $noDeal = $this->customer(8, 'sales2');
        $this->conversation($noDeal, [['in', 'text', 'Hello', '-3 hours']]);

        $this->signInAs('manager');
        $body = $this->request('GET', '/api/v1/worklist/interventions');

        $this->assertStatus(200);
        self::assertSame([$unanswered, $lead, $noShow, $idle], self::customerIds($body), 'Unanswered first, then the lead, the no-show and the idle deal; within-threshold deals and customers without a deal are left out.');
        self::assertSame(['customer_unanswered', 'lead_unattended', 'no_show_without_follow_up', 'deal_idle'], array_column($body['items'], 'reason_code'));
        $first = $body['items'][0];
        self::assertSame($unansweredDeal, $first['deal']['id']);
        self::assertSame(['minutes' => 90], $first['reason_params']);
        self::assertSame(450000, $first['at_risk_cents'], 'The deal\'s own expected gross.');
        self::assertSame($this->userId('sales'), $first['assignee']['id']);
        self::assertSame(['minutes' => 180, 'unassigned' => true], $body['items'][1]['reason_params']);
        self::assertNull($body['items'][1]['assignee'], 'The lead\'s deal has nobody.');
        self::assertSame(30, $body['items'][2]['reason_params']['hours']);
        self::assertSame(['days' => 7, 'stage' => 'engaged', 'threshold' => 6], $body['items'][3]['reason_params']);
        self::assertSame(250000, $body['items'][3]['at_risk_cents'], 'Without an expected gross, the dealership\'s average.');

        self::assertSame(4, $body['summary']['count']);
        self::assertSame(450000 + 3 * 250000, $body['summary']['at_risk_cents']);
        $reps = array_map(static fn (array $row): array => [$row['user']['id'], $row['count']], $body['summary']['reps_with_unanswered']);
        self::assertSame([[$this->userId('sales2'), 2], [$this->userId('sales'), 1]], $reps, 'Every unanswered customer counts for its rep, waiting long or not, deal or not; most first.');
    }

    public function testTheDefaultAverageGrossIsThreeThousandDollars(): void
    {
        $this->db()->executeStatement('UPDATE dealerships SET average_gross_cents = 0 WHERE id = ?', [$this->dealershipId()]);
        $idle = $this->customer(3);
        $this->deal($idle, 'proposal', '-3 days');

        $this->signInAs('manager');
        $body = $this->request('GET', '/api/v1/worklist/interventions');

        self::assertSame(300000, $body['items'][0]['at_risk_cents']);
    }

    public function testAnotherDealershipSeesNothing(): void
    {
        $ours = $this->customer(2);
        $this->conversation($ours, [['in', 'text', 'Anyone there?', '-2 hours']]);
        $this->deal($ours, 'engaged', '-1 day');

        $this->signInAs('other');
        $body = $this->request('GET', '/api/v1/worklist/interventions');

        $this->assertStatus(200);
        self::assertNotContains($ours, self::customerIds($body));
        self::assertNotContains($this->userId('sales'), array_map(static fn (array $row): string => $row['user']['id'], $body['summary']['reps_with_unanswered']));
    }
}
