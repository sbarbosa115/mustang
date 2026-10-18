<?php

namespace App\Tests\Functional\Compliance;

use App\Tests\Support\ApiTestCase;

/**
 * Helpers for the consent tests: a customer with no ledger rows and no suppressions to start from, and the ledger's
 * own API to record and revoke.
 */
abstract class ComplianceTestCase extends ApiTestCase
{
    protected function withACleanLedger(string $customerId): void
    {
        $this->db()->executeStatement(
            'DELETE FROM consents WHERE contact_value IN (SELECT value FROM customer_contacts WHERE customer_id = ?)',
            [$customerId],
        );
        $this->db()->executeStatement('DELETE FROM consents WHERE customer_id = ?', [$customerId]);
        $this->db()->executeStatement(
            'DELETE FROM suppressions WHERE contact_value IN (SELECT value FROM customer_contacts WHERE customer_id = ?)',
            [$customerId],
        );
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    protected function record(string $customerId, string $channel, string $contactValue, array $extra = []): array
    {
        return $this->request('POST', '/api/v1/customers/'.$customerId.'/consents', [
            'channel' => $channel,
            'contact_value' => $contactValue,
            'source' => 'verbal',
            ...$extra,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function ledger(string $customerId): array
    {
        return $this->request('GET', '/api/v1/customers/'.$customerId.'/consents');
    }

    /**
     * @return array<string, mixed>
     */
    protected function inbound(string $from, string $body, string $channel = 'text'): array
    {
        return $this->request('POST', '/api/v1/dev/inbound', ['from' => $from, 'channel' => $channel, 'body' => $body]);
    }

    protected function isSuppressed(string $contactValue, string $channel = 'text'): bool
    {
        return false !== $this->db()->fetchOne(
            'SELECT 1 FROM suppressions WHERE contact_value = ? AND channel = ?',
            [$contactValue, $channel],
        );
    }
}
