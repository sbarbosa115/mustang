<?php

namespace App\Tests\Functional\Lead;

use App\Messaging\Domain\Event\MessageSent;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A lead counts as contacted from the first message sent to its customer: the "contact now" list and the KPIs read
 * first_contacted_at and the contacted status.
 */
final class LeadFirstContactTest extends ApiTestCase
{
    public function testTheFirstMessageSentMarksTheCustomersOpenLeadsContacted(): void
    {
        $this->signInAs('bdc');
        $lead = $this->request('POST', '/api/v1/leads', ['source' => 'phone', 'first_name' => 'First', 'last_name' => 'Contact', 'phone' => '813-555-0911']);
        $this->assertStatus(201);
        $customerId = $lead['customer']['id'];
        static::getContainer()->get('security.token_storage')->setToken(null); // nobody signed in: the handler must not need a tenant
        $sentAt = new \DateTimeImmutable('2026-10-18 15:00:00', new \DateTimeZone('UTC'));

        $this->publish(new MessageSent('01a15200-0000-7000-8000-000000000001', $this->dealershipId(), '01a15200-0000-7000-8000-000000000002', $customerId, 'text', null, $sentAt));
        $this->publish(new MessageSent('01a15200-0000-7000-8000-000000000003', $this->dealershipId(), '01a15200-0000-7000-8000-000000000002', $customerId, 'text', null, $sentAt->modify('+1 hour')));

        $row = $this->db()->fetchAssociative('SELECT status, first_contacted_at FROM leads WHERE id = ?', [$lead['id']]);
        self::assertSame('contacted', $row['status']);
        self::assertSame('2026-10-18 15:00:00', $row['first_contacted_at'], 'The first message counts; a later one does not move it.');
    }

    public function testAClosedLeadAndAnotherDealershipsLeadAreLeftAlone(): void
    {
        $this->signInAs('bdc');
        $lead = $this->request('POST', '/api/v1/leads', ['source' => 'phone', 'first_name' => 'Closed', 'last_name' => 'Lead', 'phone' => '813-555-0912']);
        $this->request('POST', '/api/v1/leads/'.$lead['id'].'/close', []);
        $this->assertStatus(200);

        $this->publish(new MessageSent('01a15200-0000-7000-8000-000000000004', $this->dealershipId('other'), '01a15200-0000-7000-8000-000000000005', $lead['customer']['id'], 'text', null, new \DateTimeImmutable()));
        $this->publish(new MessageSent('01a15200-0000-7000-8000-000000000006', $this->dealershipId(), '01a15200-0000-7000-8000-000000000005', $lead['customer']['id'], 'text', null, new \DateTimeImmutable()));

        $row = $this->db()->fetchAssociative('SELECT status, first_contacted_at FROM leads WHERE id = ?', [$lead['id']]);
        self::assertSame('closed', $row['status']);
        self::assertNull($row['first_contacted_at']);
    }

    private function publish(object $event): void
    {
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('event.bus');
        $bus->dispatch($event);
    }
}
