<?php

namespace App\Compliance\Application\EventHandler;

use App\Compliance\Application\Command\ApplyKeyword;
use App\Compliance\Domain\Model\SmsKeyword;
use App\Messaging\Domain\Event\InboundMessageReceived;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * Reads STOP and START from every inbound text. The dealership and the customer come from the event, never from the
 * session: replies arrive from the console and the worker too.
 */
final readonly class RevokeOnStopKeyword implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(InboundMessageReceived $event): void
    {
        if ('text' !== $event->channel || null === SmsKeyword::fromMessage($event->body)) {
            return;
        }
        $this->commands->dispatch(new ApplyKeyword($event->dealershipId, $event->customerId, $event->contactValue, $event->body));
    }
}
