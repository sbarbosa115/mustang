<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Shared\Application\Port\ConsentStates;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Channel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Reads the ledger with plain SQL so any context can ask without loading Compliance entities. The rules (PRD,
 * "Consent"): per contact value and channel, the latest record decides granted or revoked, where a revoked record
 * counts from its revocation; at the same instant the record written last wins. No record is missing, and a contact
 * on the suppression list is opted out whatever the ledger says.
 */
final readonly class DbalConsentStates implements ConsentStates
{
    private const array KIND = ['text' => 'phone', 'call' => 'phone', 'email' => 'email'];

    public function __construct(private Connection $connection, private TenantContext $tenant)
    {
    }

    public function forCustomers(array $customerIds): array
    {
        if ([] === $customerIds) {
            return [];
        }
        $dealership = $this->tenant->dealershipId();
        $contacts = $this->contacts($dealership, $customerIds);
        $primaries = [];
        $all = [];
        foreach ($contacts as $kinds) {
            foreach ($kinds as $values) {
                if ([] !== $values) {
                    $primaries[] = $values[0];
                }
                array_push($all, ...$values);
            }
        }
        $latest = $this->latest($dealership, $primaries);
        $suppressed = $this->suppressed($all);

        $states = [];
        foreach ($customerIds as $id) {
            $row = [];
            foreach (self::KIND as $channel => $kind) {
                $values = $contacts[$id][$kind] ?? [];
                $row[$channel] = $this->decide($channel, $values[0] ?? null, $values, $latest, $suppressed)['state'];
            }
            /* @var array{text: string, email: string, call: string} $row */
            $states[$id] = $row;
        }

        return $states;
    }

    public function forContact(string $customerId, Channel $channel, string $contactValue): array
    {
        $dealership = $this->connection->fetchOne('SELECT dealership_id FROM customers WHERE id = :id', ['id' => $customerId]);
        if (false === $dealership) {
            return ['state' => 'missing', 'consent_id' => null];
        }
        $values = $this->contacts((string) $dealership, [$customerId])[$customerId][self::KIND[$channel->value]] ?? [];
        if (!\in_array($contactValue, $values, true)) {
            $values[] = $contactValue;
        }

        return $this->decide(
            $channel->value,
            $contactValue,
            $values,
            $this->latest((string) $dealership, [$contactValue]),
            $this->suppressed($values),
        );
    }

    /**
     * The state of a contact value on a channel in a dealership, whichever customer it was recorded on.
     *
     * @return array{state: string, consent_id: ?string}
     */
    public function forValue(string $dealershipId, string $contactValue, Channel $channel): array
    {
        return $this->decide($channel->value, $contactValue, [$contactValue], $this->latest($dealershipId, [$contactValue]), $this->suppressed([$contactValue]));
    }

    /**
     * @param list<string>                                            $values     every contact of the channel's kind
     * @param array<string, array{state: string, consent_id: string}> $latest     by "value|channel"
     * @param array<string, true>                                     $suppressed by "value|channel"
     *
     * @return array{state: string, consent_id: ?string}
     */
    private function decide(string $channel, ?string $value, array $values, array $latest, array $suppressed): array
    {
        $decided = null === $value ? null : ($latest[$value.'|'.$channel] ?? null);
        foreach ($values as $any) {
            if (isset($suppressed[$any.'|'.$channel])) {
                return ['state' => 'opted_out', 'consent_id' => $decided['consent_id'] ?? null];
            }
        }

        return $decided ?? ['state' => 'missing', 'consent_id' => null];
    }

    /**
     * @param list<string> $customerIds
     *
     * @return array<string, array{phone: list<string>, email: list<string>}> primary first
     */
    private function contacts(string $dealership, array $customerIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT customer_id, kind, value FROM customer_contacts
             WHERE dealership_id = :dealership AND customer_id IN (:ids)
             ORDER BY is_primary DESC, created_at ASC, id ASC',
            ['dealership' => $dealership, 'ids' => $customerIds],
            ['ids' => ArrayParameterType::STRING],
        );
        $contacts = [];
        foreach ($rows as $row) {
            $contacts[(string) $row['customer_id']][(string) $row['kind']][] = (string) $row['value'];
        }

        /* @var array<string, array{phone: list<string>, email: list<string>}> $contacts */
        return $contacts;
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, array{state: string, consent_id: string}> the deciding record by "value|channel"
     */
    private function latest(string $dealership, array $values): array
    {
        if ([] === $values) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, contact_value, channel, state FROM consents
             WHERE dealership_id = :dealership AND contact_value IN (:values)
             ORDER BY COALESCE(revoked_at, captured_at) ASC, created_at ASC, id ASC',
            ['dealership' => $dealership, 'values' => array_values(array_unique($values))],
            ['values' => ArrayParameterType::STRING],
        );
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row['contact_value'].'|'.$row['channel']] = ['state' => (string) $row['state'], 'consent_id' => (string) $row['id']];
        }

        return $latest;
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, true> by "value|channel"
     */
    private function suppressed(array $values): array
    {
        if ([] === $values) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT contact_value, channel FROM suppressions WHERE contact_value IN (:values)',
            ['values' => array_values(array_unique($values))],
            ['values' => ArrayParameterType::STRING],
        );
        $suppressed = [];
        foreach ($rows as $row) {
            $suppressed[$row['contact_value'].'|'.$row['channel']] = true;
        }

        return $suppressed;
    }
}
