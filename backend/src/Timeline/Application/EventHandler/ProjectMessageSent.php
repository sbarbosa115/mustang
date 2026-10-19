<?php

namespace App\Timeline\Application\EventHandler;

use App\Messaging\Domain\Event\MessageSent;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectMessageSent implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(MessageSent $event): void
    {
        $code = 'message_sent_'.$event->channel;
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::MessageOut,
            $event->occurredAt,
            \sprintf('%s message sent', ucfirst($event->channel)),
            $code,
            ActivityKey::of($code, MessageSent::class, $event->messageId, $event->occurredAt),
            'message',
            $event->messageId,
            $event->sentByUserId,
        ));
    }
}
