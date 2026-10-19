<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Domain\Event\MessageFailed;
use App\Messaging\Domain\Repository\ConversationRepository;
use App\Messaging\Domain\Repository\MessageRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;

final readonly class RecordEmailDeliveryHandler implements CommandHandler
{
    public function __construct(
        private MessageRepository $messages,
        private ConversationRepository $conversations,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RecordEmailDelivery $command): void
    {
        $message = $this->messages->getInDealership($command->messageId, $command->dealershipId);
        if ($command->sent) {
            $message->markSent($command->transportMessageId, $this->clock->now());

            return;
        }
        $reason = $command->failedReason ?? 'delivery_failed';
        $message->markFailed($reason);
        $conversation = $this->conversations->getInDealership($message->conversationId(), $command->dealershipId);
        $this->events->publish(new MessageFailed(
            $message->id(),
            $command->dealershipId,
            $conversation->id(),
            $conversation->customerId(),
            $message->channel()->value,
            $reason,
            $this->clock->now(),
        ));
    }
}
