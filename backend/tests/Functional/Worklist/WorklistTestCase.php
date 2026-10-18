<?php

namespace App\Tests\Functional\Worklist;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * The worklist is computed from the other contexts' tables, so these tests write the facts straight into them at
 * fixed times: "now" is frozen at Tuesday 20 October 2026, 18:00 UTC (14:00 in Tampa), and Mustang Motors starts
 * with no conversations, leads, deals or appointments (DAMA rolls everything back).
 */
abstract class WorklistTestCase extends ApiTestCase
{
    use ClockSensitiveTrait;

    protected const string NOW = '2026-10-20 18:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));
        $dealership = $this->dealershipId();
        foreach (['messages', 'conversations', 'leads', 'deals', 'appointments'] as $table) {
            $this->db()->executeStatement(\sprintf('DELETE FROM %s WHERE dealership_id = ?', $table), [$dealership]);
        }
    }

    /**
     * "now" moved by a modifier, as a UTC SQL datetime: at('-45 minutes').
     */
    protected function at(string $modifier): string
    {
        return new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))->modify($modifier)->format('Y-m-d H:i:s');
    }

    /**
     * Seeded customer n, owned as given (roles of ApiTestCase::ACCOUNTS, null for nobody), with only the consent
     * listed.
     *
     * @param list<string> $consented channels with granted consent
     */
    protected function customer(int $n, ?string $sales1 = 'sales', ?string $bdc = null, array $consented = []): string
    {
        $id = $this->customerId($n);
        $this->db()->executeStatement(
            'UPDATE customers SET sales1_user_id = ?, sales2_user_id = NULL, bdc_user_id = ?, archived_at = NULL WHERE id = ?',
            [null === $sales1 ? null : $this->userId($sales1), null === $bdc ? null : $this->userId($bdc), $id],
        );
        $this->db()->executeStatement('DELETE FROM consents WHERE customer_id = ?', [$id]);
        $this->db()->executeStatement('DELETE FROM suppressions WHERE contact_value IN (SELECT value FROM customer_contacts WHERE customer_id = ?)', [$id]);
        foreach ($consented as $channel) {
            $value = (string) $this->db()->fetchOne(
                'SELECT value FROM customer_contacts WHERE customer_id = ? AND kind = ? ORDER BY is_primary DESC LIMIT 1',
                [$id, 'email' === $channel ? 'email' : 'phone'],
            );
            $this->db()->executeStatement(
                "INSERT INTO consents (id, dealership_id, customer_id, contact_value, channel, state, source, captured_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'granted', 'test', ?, ?, ?)",
                [Uuid::v7()->toRfc4122(), $this->dealershipId(), $id, $value, $channel, $this->at('-30 days'), $this->at('-30 days'), $this->at('-30 days')],
            );
        }

        return $id;
    }

    /**
     * A conversation from messages given as [direction, channel, body, modifier], oldest first.
     *
     * @param list<array{string, string, string, string}> $messages
     */
    protected function conversation(string $customerId, array $messages): string
    {
        $id = Uuid::v7()->toRfc4122();
        $lastIn = $lastOut = $last = null;
        foreach ($messages as [$direction, , , $when]) {
            $at = $this->at($when);
            $last = $at;
            'in' === $direction ? $lastIn = $at : $lastOut = $at;
        }
        $this->db()->executeStatement(
            'INSERT INTO conversations (id, dealership_id, customer_id, assigned_user_id, last_message_at, last_inbound_at, last_outbound_at, unread_count, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, 0, ?, ?)',
            [$id, $this->dealershipId(), $customerId, $last, $lastIn, $lastOut, $this->at('-10 days'), $this->at('-10 days')],
        );
        foreach ($messages as [$direction, $channel, $body, $when]) {
            $this->message($id, $direction, $channel, $body, $when);
        }

        return $id;
    }

    protected function message(string $conversationId, string $direction, string $channel, string $body, string $when, ?string $status = null): void
    {
        $at = $this->at($when);
        $this->db()->executeStatement(
            "INSERT INTO messages (id, dealership_id, conversation_id, direction, channel, to_value, from_value, body, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'to', 'from', ?, ?, ?, ?)",
            [Uuid::v7()->toRfc4122(), $this->dealershipId(), $conversationId, $direction, $channel, $body, $status ?? ('in' === $direction ? 'received' : 'sent'), $at, $at],
        );
        if ('out' === $direction && \in_array($status ?? 'sent', ['sent', 'queued'], true)) {
            $this->db()->executeStatement('UPDATE conversations SET last_outbound_at = ?, last_message_at = ? WHERE id = ?', [$at, $at, $conversationId]);
        }
    }

    protected function lead(string $customerId, string $received, ?string $assignee = 'sales', string $status = 'assigned'): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->db()->executeStatement(
            "INSERT INTO leads (id, dealership_id, customer_id, source, received_at, status, assigned_user_id, vehicle_interest_text, created_at, updated_at)
             VALUES (?, ?, ?, 'website', ?, ?, ?, '2024 Toyota Camry', ?, ?)",
            [$id, $this->dealershipId(), $customerId, $this->at($received), $status, null === $assignee ? null : $this->userId($assignee), $this->at($received), $this->at($received)],
        );

        return $id;
    }

    protected function deal(string $customerId, string $stage, string $entered, ?string $assignee = 'sales', ?int $expectedGross = null, ?string $lastActivity = null): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->db()->executeStatement(
            "INSERT INTO deals (id, dealership_id, customer_id, stage, stage_entered_at, assigned_user_id, vehicle_text, expected_gross_cents, last_activity_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, '2023 Honda CR-V', ?, ?, ?, ?)",
            [$id, $this->dealershipId(), $customerId, $stage, $this->at($entered), null === $assignee ? null : $this->userId($assignee), $expectedGross, null === $lastActivity ? $this->at($entered) : $this->at($lastActivity), $this->at($entered), $this->at($entered)],
        );

        return $id;
    }

    /**
     * An appointment at a UTC instant ("2026-10-20 20:30:00") or a modifier of now ("-5 hours").
     */
    protected function appointment(string $customerId, string $at, string $status = 'scheduled', string $booked = '-3 days'): string
    {
        $id = Uuid::v7()->toRfc4122();
        $scheduled = str_starts_with($at, '+') || str_starts_with($at, '-') ? $this->at($at) : $at;
        $this->db()->executeStatement(
            "INSERT INTO appointments (id, dealership_id, customer_id, user_id, scheduled_at, duration_minutes, kind, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 60, 'test_drive', ?, ?, ?)",
            [$id, $this->dealershipId(), $customerId, $this->userId('sales'), $scheduled, $status, $this->at($booked), $this->at($booked)],
        );

        return $id;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    protected static function customerIds(array $body): array
    {
        return array_map(static fn (array $item): string => $item['customer']['id'], $body['items']);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected static function rowOf(array $body, string $customerId): array
    {
        foreach ($body['items'] as $item) {
            if ($item['customer']['id'] === $customerId) {
                return $item;
            }
        }
        self::fail('No row for customer '.$customerId);
    }
}
