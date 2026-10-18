<?php

namespace App\Messaging\Infrastructure\Adapter;

use App\Lead\Application\Command\CaptureLead;
use App\Lead\Domain\Model\LeadSource;
use App\Messaging\Application\Port\LeadIntake;
use App\Shared\Application\Bus\CommandBus;

/**
 * Through the Lead context's CaptureLead on the command bus (nested in ReceiveInboundMessage's transaction).
 */
final readonly class LeadIntakeAdapter implements LeadIntake
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function captureFirstContact(?string $phone, ?string $email, string $firstName, string $lastName, string $channelNote): string
    {
        return (string) $this->commands->dispatch(new CaptureLead(
            LeadSource::Other,
            firstName: $firstName,
            lastName: $lastName,
            phone: $phone,
            email: $email,
            channelNote: $channelNote,
        ));
    }
}
