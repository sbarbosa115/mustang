<?php

namespace App\Tests\Functional\Messaging;

use App\Tests\Support\ApiTestCase;

/**
 * Helpers for the inbox tests: a known starting point (no conversations, no consent for the customers used) and
 * consent rows written straight into the ledger, since the ledger's own API belongs to item "compliance".
 */
abstract class MessagingTestCase extends ApiTestCase
{
    /**
     * Removes the seeded conversations of Mustang Motors, so counts start at zero (DAMA rolls it back).
     */
    protected function startWithAnEmptyInbox(): void
    {
        $dealership = $this->dealershipId();
        $this->db()->executeStatement('DELETE FROM messages WHERE dealership_id = ?', [$dealership]);
        $this->db()->executeStatement('DELETE FROM conversations WHERE dealership_id = ?', [$dealership]);
    }

    protected function withoutConsent(string $customerId): void
    {
        $this->db()->executeStatement('DELETE FROM consents WHERE customer_id = ?', [$customerId]);
        $this->db()->executeStatement(
            'DELETE FROM suppressions WHERE contact_value IN (SELECT value FROM customer_contacts WHERE customer_id = ?)',
            [$customerId],
        );
    }

    protected function consent(string $customerId, string $channel, string $state = 'granted'): void
    {
        $value = (string) $this->db()->fetchOne(
            'SELECT value FROM customer_contacts WHERE customer_id = ? AND kind = ? ORDER BY is_primary DESC LIMIT 1',
            [$customerId, 'email' === $channel ? 'email' : 'phone'],
        );
        $this->db()->executeStatement(
            "INSERT INTO consents (id, dealership_id, customer_id, contact_value, channel, state, source, captured_at, created_at, updated_at)
             VALUES (UUID(), ?, ?, ?, ?, ?, 'test', NOW(), NOW(), NOW())",
            [$this->dealershipId(), $customerId, $value, $channel, $state],
        );
    }

    protected function suppress(string $contactValue, string $channel): void
    {
        $this->db()->executeStatement(
            "INSERT INTO suppressions (id, contact_value, channel, reason, created_at, updated_at) VALUES (UUID(), ?, ?, 'stop', NOW(), NOW())",
            [$contactValue, $channel],
        );
    }

    /**
     * The customer's conversation id, created through the API if there is none.
     */
    protected function conversationOf(string $customerId): string
    {
        $body = $this->request('GET', '/api/v1/customers/'.$customerId.'/conversation');
        $this->assertStatus(200, 'The customer\'s conversation is found or created.');

        return (string) $body['conversation']['id'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function inbound(string $from, string $body, string $channel = 'text', ?string $subject = null): array
    {
        return $this->request('POST', '/api/v1/dev/inbound', ['from' => $from, 'channel' => $channel, 'body' => $body, 'subject' => $subject]);
    }

    /**
     * Moves a conversation's last inbound message back in time, as if the customer wrote $minutes ago.
     */
    protected function customerWroteMinutesAgo(string $conversationId, int $minutes): void
    {
        $this->db()->executeStatement(
            'UPDATE conversations SET last_inbound_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE), last_message_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE) WHERE id = ?',
            [$minutes, $minutes, $conversationId],
        );
    }
}
