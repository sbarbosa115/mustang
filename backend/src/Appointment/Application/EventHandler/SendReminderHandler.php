<?php

namespace App\Appointment\Application\EventHandler;

use App\Appointment\Application\Command\DeliverReminder;
use App\Appointment\Application\Command\SendReminder;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * The worker receives the delayed message on the event bus, which has no transaction: the work is a command.
 */
final readonly class SendReminderHandler implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(SendReminder $message): void
    {
        $this->commands->dispatch(new DeliverReminder($message->appointmentId, $message->dealershipId, $message->scheduledAt));
    }
}
