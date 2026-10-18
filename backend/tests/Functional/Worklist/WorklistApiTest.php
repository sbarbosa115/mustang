<?php

namespace App\Tests\Functional\Worklist;

final class WorklistApiTest extends WorklistTestCase
{
    public function testARepSeesOnlyTheirCustomersOneReasonEachMostUrgentFirst(): void
    {
        $question = $this->customer(2, consented: ['text']);
        $this->conversation($question, [['out', 'text', 'Thanks for stopping by.', '-2 days'], ['in', 'text', 'Do you take trade-ins?', '-45 minutes']]);
        $this->deal($question, 'engaged', '-6 days');
        $lead = $this->customer(3, consented: ['email']);
        $this->lead($lead, '-20 minutes');
        $appointment = $this->customer(4);
        $appointmentId = $this->appointment($appointment, '2026-10-20 20:30:00');
        $noShow = $this->customer(5);
        $this->appointment($noShow, '-5 hours', 'no_show');
        $idle = $this->customer(6);
        $idleDeal = $this->deal($idle, 'proposal', '-4 days');
        $someoneElses = $this->customer(7, 'sales2');
        $this->conversation($someoneElses, [['in', 'text', 'Hello?', '-2 hours']]);
        $quiet = $this->customer(8);
        $this->deal($quiet, 'engaged', '-1 day');

        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/worklist/contact-now');

        $this->assertStatus(200);
        self::assertSame($this->userId('sales'), $body['user']['id'], 'Without user_id it is the caller\'s own list.');
        self::assertSame([$question, $lead, $appointment, $noShow, $idle], self::customerIds($body), 'Question 165, lead 110, appointment 80, no-show 70, idle deal 54; sales2\'s customer and the quiet deal are left out.');
        self::assertSame(5, $body['total']);

        $first = $body['items'][0];
        self::assertSame('unanswered_inbound', $first['reason_code'], 'The question beats the idle deal of the same customer: one reason per row.');
        self::assertSame(['minutes' => 45, 'channel' => 'text', 'question' => true], $first['reason_params']);
        self::assertSame(165, $first['score']);
        self::assertTrue($first['hot'], 'Unanswered for more than 30 minutes.');
        self::assertSame('text', $first['suggested_channel'], 'The customer wrote by text.');
        self::assertSame(['text' => 'granted', 'email' => 'missing', 'call' => 'missing'], $first['channel_availability']);
        self::assertSame('engaged', $first['deal']['stage']);
        self::assertSame(6, $first['deal']['days_in_stage']);
        self::assertSame('2023 Honda CR-V', $first['deal']['vehicle_text']);
        self::assertSame('+18135551002', $first['phone']);
        self::assertSame('2026-10-20T17:15:00+00:00', $first['since']);

        $leadRow = $body['items'][1];
        self::assertSame(['minutes' => 20, 'source' => 'website', 'vehicle' => '2024 Toyota Camry'], $leadRow['reason_params']);
        self::assertFalse($leadRow['hot'], 'Hot is kept for unanswered customers.');
        self::assertSame('email', $leadRow['suggested_channel'], 'No inbound and no text consent: email, which is consented.');

        $appointmentRow = $body['items'][2];
        self::assertSame('appointment_today_unconfirmed', $appointmentRow['reason_code']);
        self::assertSame(['id' => $appointmentId, 'scheduled_at' => '2026-10-20T20:30:00+00:00', 'status' => 'scheduled'], $appointmentRow['appointment']);
        self::assertSame('call', $appointmentRow['suggested_channel'], 'Nothing consented: a call.');

        self::assertSame('no_show_without_follow_up', $body['items'][3]['reason_code']);
        self::assertSame('no_show', $body['items'][3]['appointment']['status'], 'The row shows the missed appointment.');
        self::assertSame(['days' => 4, 'stage' => 'proposal', 'threshold' => 1], $body['items'][4]['reason_params']);
        self::assertSame($idleDeal, $body['items'][4]['deal']['id']);
    }

    public function testARowBelongsToSalesOneElseTheDealAssigneeElseBdc(): void
    {
        $byDeal = $this->customer(2, null, 'bdc');
        $this->deal($byDeal, 'engaged', '-5 days', 'sales');
        $byBdc = $this->customer(3, null, 'bdc');
        $this->lead($byBdc, '-10 minutes', null, 'new');
        $bySalesOne = $this->customer(4, 'sales2', 'bdc');
        $this->deal($bySalesOne, 'engaged', '-5 days', 'sales');

        $this->signInAs('sales');
        self::assertSame([$byDeal], self::customerIds($this->request('GET', '/api/v1/worklist/contact-now')), 'No Sales 1: the open deal\'s assignee; Sales 1 wins over the deal.');
        $this->signInAs('bdc');
        self::assertSame([$byBdc], self::customerIds($this->request('GET', '/api/v1/worklist/contact-now')), 'No Sales 1 and no deal: the BDC agent.');
        $this->signInAs('sales2');
        self::assertSame([$bySalesOne], self::customerIds($this->request('GET', '/api/v1/worklist/contact-now')));
    }

    public function testRowsLeaveTheListOnceHandled(): void
    {
        $answered = $this->customer(2);
        $this->conversation($answered, [['in', 'email', 'Is it still available?', '-50 minutes'], ['out', 'email', 'Yes, it is.', '-10 minutes']]);
        $contacted = $this->customer(3);
        $this->lead($contacted, '-40 minutes');
        $conversation = $this->conversation($contacted, [['out', 'text', 'Hi, this is Sam from Mustang Motors.', '-30 minutes']]);
        $blocked = $this->customer(4);
        $this->lead($blocked, '-40 minutes');
        $this->message($this->conversation($blocked, []), 'out', 'text', 'Hi!', '-30 minutes', 'blocked');
        $followedUp = $this->customer(5);
        $this->appointment($followedUp, '-6 hours', 'no_show');
        $this->appointment($followedUp, '+2 days', 'scheduled', '-1 hour');
        $confirmed = $this->customer(6);
        $this->appointment($confirmed, '2026-10-20 21:00:00', 'confirmed');
        $archived = $this->customer(7);
        $this->lead($archived, '-5 minutes');
        $this->db()->executeStatement('UPDATE customers SET archived_at = ? WHERE id = ?', [$this->at('-1 minute'), $archived]);
        unset($conversation);

        $this->signInAs('sales');
        $body = $this->request('GET', '/api/v1/worklist/contact-now');

        self::assertSame([$blocked], self::customerIds($body), 'Answered, contacted, rebooked, confirmed and archived customers need nobody; a blocked send is no contact.');
        self::assertSame('new_lead_uncontacted', $body['items'][0]['reason_code']);
    }

    public function testTodayIsTheDealershipsDay(): void
    {
        $tonight = $this->customer(2);
        $this->appointment($tonight, '2026-10-21 01:00:00'); // 21:00 on the 20th in Tampa
        $tomorrow = $this->customer(3);
        $this->appointment($tomorrow, '2026-10-21 05:00:00'); // 01:00 on the 21st in Tampa

        $this->signInAs('sales');
        self::assertSame([$tonight], self::customerIds($this->request('GET', '/api/v1/worklist/contact-now')), 'Today runs midnight to midnight in America/New_York.');
    }

    public function testAManagerCanPickARepAndARepCannot(): void
    {
        $theirs = $this->customer(2, 'sales2');
        $this->lead($theirs, '-15 minutes', 'sales2');

        $this->signInAs('manager');
        $body = $this->request('GET', '/api/v1/worklist/contact-now?user_id='.$this->userId('sales2'));
        $this->assertStatus(200);
        self::assertSame(['id' => $this->userId('sales2'), 'name' => $body['user']['name']], $body['user']);
        self::assertNotSame('', $body['user']['name']);
        self::assertSame([$theirs], self::customerIds($body));

        $this->signInAs('sales');
        $refused = $this->request('GET', '/api/v1/worklist/contact-now?user_id='.$this->userId('sales2'));
        $this->assertError(403, 'worklist_not_yours', $refused, 'A rep sees only their own list.');
        $this->request('GET', '/api/v1/worklist/contact-now?user_id='.$this->userId('sales'));
        $this->assertStatus(200, 'Asking for one\'s own list by id is fine.');
        $this->request('GET', '/api/v1/worklist/contact-now?user_id=not-a-uuid');
        $this->assertStatus(422);
    }

    public function testAnotherDealershipSeesNothing(): void
    {
        $ours = $this->customer(2);
        $this->lead($ours, '-15 minutes');

        $this->signInAs('other');
        $body = $this->request('GET', '/api/v1/worklist/contact-now');
        $this->assertStatus(200);
        self::assertNotContains($ours, self::customerIds($body));

        $this->request('GET', '/api/v1/worklist/contact-now?user_id='.$this->userId('sales'));
        $this->assertStatus(404, 'Another dealership\'s user is not found, even for its admin.');
    }
}
