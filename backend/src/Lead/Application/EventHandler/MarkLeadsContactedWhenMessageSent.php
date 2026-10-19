<?php

namespace App\Lead\Application\EventHandler;

use App\Lead\Application\Command\RecordLeadContact;
use App\Messaging\Domain\Event\MessageSent;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * The first message sent to a customer contacts their open leads (first_contacted_at, status contacted), which the
 * "contact now" list and the KPIs read. The event bus has no transaction, so the work runs as a command.
 */
final readonly class MarkLeadsContactedWhenMessageSent implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(MessageSent $event): void
    {
        $this->commands->dispatch(new RecordLeadContact($event->dealershipId, $event->customerId, $event->occurredAt));
    }
}
