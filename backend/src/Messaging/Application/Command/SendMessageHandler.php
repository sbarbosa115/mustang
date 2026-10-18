<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Application\Port\ConsentCheck;
use App\Messaging\Application\Port\CustomerContacts;
use App\Messaging\Application\Port\MessageTransport;
use App\Messaging\Application\Port\OutboundMessage;
use App\Messaging\Domain\Event\MessageFailed;
use App\Messaging\Domain\Event\MessageSent;
use App\Messaging\Domain\Model\Message;
use App\Messaging\Domain\Model\MessageDirection;
use App\Messaging\Domain\Model\MessageStatus;
use App\Messaging\Domain\Repository\ConversationRepository;
use App\Messaging\Domain\Repository\MessageRepository;
use App\Messaging\Domain\Repository\MessageTemplateRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;

/**
 * The send pipeline: the customer's contact for the channel, the consent check, the transport, provenance.
 */
final readonly class SendMessageHandler implements CommandHandler
{
    public function __construct(
        private ConversationRepository $conversations,
        private MessageRepository $messages,
        private MessageTemplateRepository $templates,
        private CustomerContacts $contacts,
        private ConsentCheck $consent,
        private MessageTransport $transport,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(SendMessage $command): SendResult
    {
        $conversation = $this->conversations->get($command->conversationId);
        if (Channel::Call === $command->channel) {
            throw new InvalidValue('channel_not_sendable', 'A call is not sent from the inbox.', ['channel' => 'call']);
        }
        $subject = null === $command->subject || '' === trim($command->subject) ? null : trim($command->subject);
        if (Channel::Email === $command->channel && null === $subject) {
            throw new InvalidValue('subject_required', 'An email needs a subject.');
        }
        if (null !== $command->templateId) {
            $this->templates->get($command->templateId);
        }

        $dealership = $conversation->dealershipId();
        $customer = $conversation->customerId();
        $to = $this->contacts->contactsOf($dealership, $customer)[Channel::Email === $command->channel ? 'email' : 'phone'];
        if (null === $to) {
            throw new Conflict('contact_missing', 'The customer has no contact for this channel.', ['channel' => $command->channel->value]);
        }

        $now = $this->clock->now();
        $decision = $this->consent->check($customer, $command->channel, $to);
        $message = new Message(
            $dealership,
            $conversation->id(),
            MessageDirection::Out,
            $command->channel,
            $to,
            $this->transport->senderFor($command->channel),
            $command->body,
            $decision->allowsSending() ? MessageStatus::Queued : MessageStatus::Blocked,
            subject: Channel::Email === $command->channel ? $subject : null,
            sentByUserId: $command->senderUserId,
            templateId: $command->templateId,
            consentId: $decision->consentId,
            consentStateSnapshot: $decision->snapshot(),
            failedReason: $decision->allowsSending() ? null : $decision->refusalCode(),
        );
        $this->messages->add($message);

        if (!$decision->allowsSending()) {
            return new SendResult($message->id(), $decision->refusalCode(), $decision->snapshot());
        }

        $receipt = $this->transport->send(new OutboundMessage(
            $message->id(),
            $dealership,
            $command->channel,
            $to,
            $message->fromValue(),
            $command->body,
            $message->subject(),
        ));
        $message->handedToTransport($receipt->transport, $receipt->status, $receipt->transportMessageId, $receipt->failedReason, $now);

        if (MessageStatus::Failed === $receipt->status) {
            $this->events->publish(new MessageFailed($message->id(), $dealership, $conversation->id(), $customer, $command->channel->value, (string) $receipt->failedReason, $now));

            return new SendResult($message->id());
        }

        $conversation->recordOutbound($now);
        $conversation->assignIfUnassigned($command->senderUserId);
        $this->events->publish(new MessageSent($message->id(), $dealership, $conversation->id(), $customer, $command->channel->value, $command->senderUserId, $now));

        return new SendResult($message->id());
    }
}
