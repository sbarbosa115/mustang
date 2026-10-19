<?php

namespace App\Tests\Functional\Audit;

use App\Tests\Support\ApiTestCase;

/**
 * Reads audit_entries straight from the database, so a test sees exactly what the recorder wrote.
 */
abstract class AuditTestCase extends ApiTestCase
{
    /**
     * The entries of a record, oldest first; `changes` is empty for a read.
     *
     * @return list<array{action: string, entity_type: string, entity_id: string, dealership_id: string, actor_user_id: ?string, customer_id: ?string, changes: array<string, array{old: mixed, new: mixed}>, request_id: ?string, ip: ?string, has_changes: bool}>
     *
     * @phpstan-impure
     */
    protected function entries(string $entityType, ?string $entityId = null, ?string $action = null): array
    {
        $sql = 'SELECT action, entity_type, entity_id, dealership_id, actor_user_id, customer_id, changes_json, request_id, ip
                FROM audit_entries WHERE entity_type = :type';
        $params = ['type' => $entityType];
        if (null !== $entityId) {
            $sql .= ' AND entity_id = :id';
            $params['id'] = $entityId;
        }
        if (null !== $action) {
            $sql .= ' AND action = :action';
            $params['action'] = $action;
        }
        $rows = $this->db()->fetchAllAssociative($sql.' ORDER BY occurred_at, id', $params);

        return array_map(static function (array $row): array {
            $hasChanges = null !== $row['changes_json'];
            $changes = $hasChanges ? json_decode((string) $row['changes_json'], true, flags: \JSON_THROW_ON_ERROR) : [];
            unset($row['changes_json']);

            /* @var array{action: string, entity_type: string, entity_id: string, dealership_id: string, actor_user_id: ?string, customer_id: ?string, changes: array<string, array{old: mixed, new: mixed}>, request_id: ?string, ip: ?string, has_changes: bool} */
            return $row + ['changes' => $changes, 'has_changes' => $hasChanges];
        }, $rows);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    protected function requestWithId(string $method, string $path, ?array $body, string $requestId): array
    {
        $this->client->request(
            $method,
            $path,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUEST_ID' => $requestId],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
        $content = (string) $this->client->getResponse()->getContent();

        return '' === $content ? [] : (array) json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
