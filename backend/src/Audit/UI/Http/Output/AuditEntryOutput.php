<?php

namespace App\Audit\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;
use OpenApi\Attributes as OA;

final readonly class AuditEntryOutput
{
    /**
     * @param array<string, mixed>|null $changes
     */
    public function __construct(
        public string $id,
        public \App\Audit\Domain\Model\AuditAction $action,
        public string $entityType,
        public string $entityId,
        public \DateTimeImmutable $occurredAt,
        public ?UserRefOutput $actor = null,
        public ?string $customerId = null,
        #[OA\Property(type: 'object', additionalProperties: true)]
        public ?array $changes = null,
        public ?string $ip = null,
    ) {
    }
}
