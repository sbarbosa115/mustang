<?php

namespace App\DataImport\Infrastructure\Adapter;

use App\DataImport\Application\Port\LeadIntake;
use App\Lead\Application\Command\CaptureLead;
use App\Lead\Domain\Model\LeadSource;
use App\Shared\Application\Bus\CommandBus;
use Doctrine\DBAL\Connection;

/**
 * Through the Lead context's CaptureLead, which matches or creates the customer, routes the lead and raises
 * LeadCaptured.
 */
final readonly class LeadIntakeAdapter implements LeadIntake
{
    public function __construct(private CommandBus $commands, private Connection $connection)
    {
    }

    public function hasOpenLead(string $dealershipId, string $customerId): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM leads WHERE dealership_id = :dealership AND customer_id = :customer AND status <> 'closed' LIMIT 1",
            ['dealership' => $dealershipId, 'customer' => $customerId],
        );
    }

    public function capture(
        string $source,
        ?string $customerId,
        ?string $firstName,
        ?string $lastName,
        ?string $phone,
        ?string $email,
        ?string $channelNote,
        ?string $vehicleInterest,
        ?string $assigneeId,
        ?\DateTimeImmutable $receivedAt,
    ): string {
        return (string) $this->commands->dispatch(new CaptureLead(
            LeadSource::from($source),
            $customerId,
            $firstName,
            $lastName,
            $phone,
            $email,
            $channelNote,
            $vehicleInterest,
            $assigneeId,
            $receivedAt,
        ));
    }
}
