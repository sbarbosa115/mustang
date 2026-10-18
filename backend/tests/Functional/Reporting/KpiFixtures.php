<?php

namespace App\Tests\Functional\Reporting;

use App\Shared\Domain\Id;
use Doctrine\DBAL\Connection;

/**
 * Rows with fixed UTC times for the KPI tests, inserted straight into the tables the KPIs read. Nothing here goes
 * through the app, so the seed's relative times and the app's own rules cannot move a figure.
 *
 * The scenario is a 3-day period, 2025-03-08 to 2025-03-10 in America/New_York, which holds the DST change of
 * Sunday 2025-03-09 (02:00 EST becomes 03:00 EDT): the period starts at 05:00Z on the 8th (EST) and ends at 04:00Z
 * on the 11th (EDT), 71 hours later.
 */
trait KpiFixtures
{
    abstract protected function db(): Connection;

    protected function newDealership(string $name, string $timezone = 'America/New_York'): string
    {
        $id = Id::new();
        $this->db()->insert('dealerships', ['id' => $id, 'name' => $name, 'timezone' => $timezone, 'average_gross_cents' => 300000, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00']);

        return $id;
    }

    protected function newUser(string $dealership, string $role, bool $active = true): string
    {
        $id = Id::new();
        $this->db()->insert('users', [
            'id' => $id, 'dealership_id' => $dealership, 'email' => $id.'@kpi.test', 'password_hash' => 'x', 'first_name' => 'Kpi', 'last_name' => $role,
            'role' => $role, 'active' => $active ? 1 : 0, 'theme' => 'system', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00',
        ]);

        return $id;
    }

    /**
     * A customer's conversation, found or created; returns its id.
     */
    protected function conversation(string $dealership, string $customer): string
    {
        $existing = $this->db()->fetchOne('SELECT id FROM conversations WHERE dealership_id = ? AND customer_id = ?', [$dealership, $customer]);
        if (false !== $existing) {
            return (string) $existing;
        }
        $id = Id::new();
        $this->db()->insert('conversations', ['id' => $id, 'dealership_id' => $dealership, 'customer_id' => $customer, 'unread_count' => 0, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00']);

        return $id;
    }

    protected function lead(string $dealership, string $customer, string $receivedAt): string
    {
        $id = Id::new();
        $this->db()->insert('leads', ['id' => $id, 'dealership_id' => $dealership, 'customer_id' => $customer, 'source' => 'website', 'received_at' => $receivedAt, 'status' => 'new', 'created_at' => $receivedAt, 'updated_at' => $receivedAt]);

        return $id;
    }

    protected function outbound(string $dealership, string $customer, string $sentAt, string $status = 'sent'): void
    {
        $this->db()->insert('messages', [
            'id' => Id::new(), 'dealership_id' => $dealership, 'conversation_id' => $this->conversation($dealership, $customer), 'direction' => 'out', 'channel' => 'text',
            'to_value' => '+18135550000', 'from_value' => '+18135559999', 'body' => 'Hello', 'status' => $status, 'sent_at' => 'sent' === $status ? $sentAt : null,
            'created_at' => $sentAt, 'updated_at' => $sentAt,
        ]);
    }

    protected function inbound(string $dealership, string $customer, string $receivedAt): void
    {
        $this->db()->insert('messages', [
            'id' => Id::new(), 'dealership_id' => $dealership, 'conversation_id' => $this->conversation($dealership, $customer), 'direction' => 'in', 'channel' => 'text',
            'to_value' => '+18135559999', 'from_value' => '+18135550000', 'body' => 'Hi', 'status' => 'received', 'received_at' => $receivedAt,
            'created_at' => $receivedAt, 'updated_at' => $receivedAt,
        ]);
    }

    protected function appointment(string $dealership, string $customer, string $user, string $status, string $createdAt): void
    {
        $this->db()->insert('appointments', [
            'id' => Id::new(), 'dealership_id' => $dealership, 'customer_id' => $customer, 'user_id' => $user, 'scheduled_at' => '2025-04-01 15:00:00', 'duration_minutes' => 60,
            'kind' => 'visit', 'status' => $status, 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    protected function deal(string $dealership, string $customer, ?string $assignee, ?string $soldAt = null, ?string $deliveredAt = null, ?string $leadId = null): void
    {
        $this->db()->insert('deals', [
            'id' => Id::new(), 'dealership_id' => $dealership, 'customer_id' => $customer, 'stage' => null !== $deliveredAt ? 'delivered' : (null !== $soldAt ? 'sold' : 'new'),
            'stage_entered_at' => '2025-03-01 00:00:00', 'lead_id' => $leadId, 'assigned_user_id' => $assignee, 'sold_at' => $soldAt, 'delivered_at' => $deliveredAt,
            'created_at' => '2025-03-01 00:00:00', 'updated_at' => '2025-03-01 00:00:00',
        ]);
    }

    /**
     * The scenario in the class comment, for one dealership. Returns the two salespeople and the manager.
     *
     * Period leads: A (all the way to a sale), B (answered in 30 minutes, never replied), C (answered after 25 hours,
     * replied, appointment cancelled), D (last minute of the 10th local, untouched). Outside it: E (00:30 local on
     * the 11th) and F (23:59 local on the 7th). G has no lead: two texts in a row, answered after 20 minutes.
     * Previous period (5th to 7th): lead H, untouched.
     *
     * @return array{sales1: string, sales2: string, manager: string}
     */
    protected function scenario(string $d): array
    {
        $sales1 = $this->newUser($d, 'sales');
        $sales2 = $this->newUser($d, 'sales');
        $manager = $this->newUser($d, 'manager');
        $this->newUser($d, 'sales', active: false);
        [$a, $b, $c, $dd, $e, $f, $g, $h] = array_map(static fn () => Id::new(), range(1, 8));

        $leadA = $this->lead($d, $a, '2025-03-08 14:00:00');
        $this->outbound($d, $a, '2025-03-08 14:10:00');
        $this->inbound($d, $a, '2025-03-08 15:00:00');
        $this->appointment($d, $a, $sales1, 'shown', '2025-03-08 15:30:00');
        $this->deal($d, $a, $sales1, soldAt: '2025-03-20 15:00:00', leadId: $leadA);

        $this->lead($d, $b, '2025-03-09 15:00:00');
        $this->outbound($d, $b, '2025-03-09 15:30:00');

        $this->lead($d, $c, '2025-03-10 12:00:00');
        $this->outbound($d, $c, '2025-03-11 13:00:00');
        $this->inbound($d, $c, '2025-03-11 14:00:00');
        $this->appointment($d, $c, $sales2, 'cancelled', '2025-03-11 14:30:00');

        $this->lead($d, $dd, '2025-03-11 03:59:00');
        $this->lead($d, $e, '2025-03-11 04:30:00');
        $this->lead($d, $f, '2025-03-08 04:59:00');
        $this->lead($d, $h, '2025-03-06 15:00:00');

        $this->inbound($d, $g, '2025-03-09 18:00:00');
        $this->inbound($d, $g, '2025-03-09 18:05:00');
        $this->outbound($d, $g, '2025-03-09 18:20:00');

        // A failed attempt and a refused one never count as a reply.
        $this->outbound($d, $dd, '2025-03-11 03:30:00', 'failed');

        // Delivered: two to sales1, one to the manager; the one on the 11th at 00:30 local is outside the period.
        $this->deal($d, Id::new(), $sales1, deliveredAt: '2025-03-09 10:00:00');
        $this->deal($d, Id::new(), $sales1, deliveredAt: '2025-03-10 20:00:00');
        $this->deal($d, Id::new(), $manager, deliveredAt: '2025-03-08 05:00:00');
        $this->deal($d, Id::new(), $sales2, deliveredAt: '2025-03-11 04:30:00');

        return ['sales1' => $sales1, 'sales2' => $sales2, 'manager' => $manager];
    }
}
