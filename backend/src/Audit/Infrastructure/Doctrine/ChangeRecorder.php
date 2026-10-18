<?php

namespace App\Audit\Infrastructure\Doctrine;

use App\Audit\Application\Port\RequestContext;
use App\Audit\Domain\Model\AuditAction;
use App\Audit\Domain\Model\AuditEntry;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotAllowed;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * NFR-04: every create, update and delete of an entity is appended to the audit log inside the same flush, so the
 * change and its entry commit or roll back together. It records the column diffs (secrets masked), who acted (the
 * signed-in user, the user a console command or the worker acts as, or nobody), the record's dealership, the customer
 * it belongs to, and the request's id and address.
 *
 * Entities without a dealership: a Dealership is its own; a Suppression (FR-12, across every rooftop) is recorded
 * under the acting dealership and skipped when nobody acts for one. AuditEntry itself is never recorded.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class ChangeRecorder
{
    public const string MASK = '[masked]';

    /** Columns whose values never enter the log: that they changed is recorded, not what they hold. */
    private const array MASKED = ['password_hash', 'secret_encrypted', 'recovery_codes_json'];

    /** Bookkeeping that changes with everything and says nothing. */
    private const array IGNORED = ['id', 'dealership_id', 'created_at', 'updated_at'];

    private bool $paused = false;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RequestContext $request,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Stops recording until resume() (seeding the demo data).
     */
    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->paused) {
            return;
        }
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $pending = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $pending[] = [AuditAction::Create, $entity, $uow->getEntityChangeSet($entity)];
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $pending[] = [AuditAction::Update, $entity, $uow->getEntityChangeSet($entity)];
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $pending[] = [AuditAction::Delete, $entity, self::removed($uow->getOriginalEntityData($entity))];
        }
        if ([] === $pending) {
            return;
        }

        $context = null;
        $entryMetadata = $em->getClassMetadata(AuditEntry::class);
        foreach ($pending as [$action, $entity, $changeSet]) {
            if ($entity instanceof AuditEntry) {
                continue;
            }
            $metadata = $em->getClassMetadata($entity::class);
            $changes = $this->changes($metadata, $changeSet, $em);
            if (AuditAction::Update === $action && [] === $changes) {
                continue;
            }
            $dealershipId = $this->dealershipOf($entity, $metadata);
            if (null === $dealershipId) {
                continue;
            }
            $context ??= $this->context();
            $entry = new AuditEntry(
                $dealershipId,
                $action,
                $metadata->getReflectionClass()->getShortName(),
                self::idOf($metadata, $entity),
                $context['at'],
                $context['actor'],
                $this->customerOf($entity, $metadata, $changeSet, $em),
                $changes,
                $context['request'],
                $context['ip'],
            );
            $em->persist($entry);
            $uow->computeChangeSet($entryMetadata, $entry);
        }
    }

    /**
     * @return array{at: \DateTimeImmutable, actor: ?string, request: ?string, ip: ?string}
     */
    private function context(): array
    {
        return [
            'at' => $this->clock->now(),
            'actor' => $this->tenant->userId(),
            'request' => $this->request->requestId(),
            'ip' => $this->request->ip(),
        ];
    }

    /**
     * A deleted record's values, as a change set from those values to nothing.
     *
     * @param array<string, mixed> $original
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private static function removed(array $original): array
    {
        return array_map(static fn (mixed $value): array => [$value, null], $original);
    }

    /**
     * Column name => {old, new}, scalars only, secrets masked.
     *
     * @param ClassMetadata<object>                    $metadata
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function changes(ClassMetadata $metadata, array $changeSet, EntityManagerInterface $em): array
    {
        $changes = [];
        foreach ($changeSet as $field => [$old, $new]) {
            $column = self::columnOf($metadata, $field);
            if (null === $column || \in_array($column, self::IGNORED, true)) {
                continue;
            }
            $old = $this->scalar($old, $em);
            $new = $this->scalar($new, $em);
            if ($old === $new) {
                continue;
            }
            if (\in_array($column, self::MASKED, true)) {
                $old = null === $old ? null : self::MASK;
                $new = null === $new ? null : self::MASK;
            }
            $changes[$column] = ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private static function columnOf(ClassMetadata $metadata, string $field): ?string
    {
        if ($metadata->hasField($field)) {
            return $metadata->getColumnName($field);
        }
        if ($metadata->hasAssociation($field) && $metadata->isAssociationWithSingleJoinColumn($field)) {
            return $metadata->getSingleAssociationJoinColumnName($field);
        }

        // A to-many side is not a column: the records on the other side are recorded themselves.
        return null;
    }

    private function scalar(mixed $value, EntityManagerInterface $em): mixed
    {
        return match (true) {
            null === $value, \is_scalar($value) => $value,
            $value instanceof \DateTimeInterface => \DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            \is_array($value) => array_map(fn (mixed $item): mixed => $this->scalar($item, $em), $value),
            \is_object($value) && !$em->getMetadataFactory()->isTransient($value::class) => self::idOf($em->getClassMetadata($value::class), $value),
            $value instanceof \Stringable => (string) $value,
            default => get_debug_type($value),
        };
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private static function idOf(ClassMetadata $metadata, object $entity): string
    {
        return implode('|', array_map(static fn (mixed $id): string => \is_scalar($id) ? (string) $id : get_debug_type($id), $metadata->getIdentifierValues($entity)));
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function dealershipOf(object $entity, ClassMetadata $metadata): ?string
    {
        if ('dealerships' === $metadata->getTableName()) {
            return self::idOf($metadata, $entity);
        }
        if (method_exists($entity, 'dealershipId')) {
            $id = $entity->dealershipId();

            return \is_string($id) ? $id : null;
        }
        try {
            return $this->tenant->dealershipId();
        } catch (NotAllowed) {
            return null;
        }
    }

    /**
     * The customer the record belongs to, so a customer's whole history can be read: the customer itself, or the
     * record's customer_id column or association.
     *
     * @param ClassMetadata<object>                    $metadata
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     */
    private function customerOf(object $entity, ClassMetadata $metadata, array $changeSet, EntityManagerInterface $em): ?string
    {
        if ('customers' === $metadata->getTableName()) {
            return self::idOf($metadata, $entity);
        }
        foreach ($metadata->getFieldNames() as $field) {
            if ('customer_id' === $metadata->getColumnName($field)) {
                $value = $metadata->getFieldValue($entity, $field) ?? ($changeSet[$field][0] ?? null);

                return \is_string($value) ? $value : null;
            }
        }
        foreach ($metadata->getAssociationNames() as $field) {
            if ($metadata->isAssociationWithSingleJoinColumn($field) && 'customer_id' === $metadata->getSingleAssociationJoinColumnName($field)) {
                $value = $this->scalar($metadata->getFieldValue($entity, $field), $em);

                return \is_string($value) ? $value : null;
            }
        }

        return null;
    }
}
