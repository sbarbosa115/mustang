<?php

namespace App\Deal\Application\EventHandler;

use App\Deal\Application\Command\RecordCustomerContact;
use App\Messaging\Domain\Event\InboundMessageReceived;
use App\Messaging\Domain\Event\MessageSent;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * A text or email either way engages a deal still in new, and counts as activity on the customer's open deal.
 */
final readonly class EngageWhenMessageExchanged implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(InboundMessageReceived|MessageSent $event): void
    {
        $this->commands->dispatch(new RecordCustomerContact(
            $event->dealershipId,
            $event->customerId,
            $event->occurredAt,
            $event instanceof MessageSent ? $event->sentByUserId : null,
        ));
    }
}
