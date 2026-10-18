<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Application\Port\CustomerContacts;
use App\Messaging\Application\Port\LeadIntake;
use App\Messaging\Application\Port\MessageTransport;
use App\Messaging\Domain\Event\InboundMessageReceived;
use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Model\Message;
use App\Messaging\Domain\Model\MessageDirection;
use App\Messaging\Domain\Model\MessageStatus;
use App\Messaging\Domain\Repository\ConversationRepository;
use App\Messaging\Domain\Repository\MessageRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Email;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\PhoneNumber;

/**
 * Finds the customer by the contact the reply came from, finds or opens their conversation, appends the message
 * and raises InboundMessageReceived (Compliance reads STOP and START from it). A contact no customer of the dealership
 * has is a first contact: the Lead context captures a lead (source other), which creates the customer, and the message
 * goes to that customer's new conversation. The lead capture reads the dealership from TenantContext, so the command's
 * dealership must be the signed-in (or acting) user's.
 */
final readonly class ReceiveInboundMessageHandler implements CommandHandler
{
    public function __construct(
        private ConversationRepository $conversations,
        private MessageRepository $messages,
        private CustomerContacts $contacts,
        private LeadIntake $leads,
        private MessageTransport $transport,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReceiveInboundMessage $command): InboundResult
    {
        [$kind, $from] = self::contact($command->channel, $command->from);
        $customer = $this->contacts->customerWith($command->dealershipId, $kind, $from)
            ?? $this->firstContact($command->dealershipId, $command->channel, $kind, $from);

        $conversation = $this->conversations->findByCustomer($command->dealershipId, $customer);
        if (null === $conversation) {
            $conversation = new Conversation($command->dealershipId, $customer, $this->contacts->defaultAssignee($command->dealershipId, $customer));
            $this->conversations->add($conversation);
        }

        $now = $this->clock->now();
        $subject = null === $command->subject || '' === trim($command->subject) ? null : trim($command->subject);
        $message = new Message(
            $command->dealershipId,
            $conversation->id(),
            MessageDirection::In,
            $command->channel,
            $this->transport->senderFor($command->channel),
            $from,
            $command->body,
            MessageStatus::Received,
            subject: Channel::Email === $command->channel ? $subject : null,
            receivedAt: $now,
        );
        $this->messages->add($message);
        $conversation->recordInbound($now);

        $this->events->publish(new InboundMessageReceived(
            $message->id(),
            $command->dealershipId,
            $conversation->id(),
            $customer,
            $command->channel->value,
            $from,
            $command->body,
            $now,
        ));

        return new InboundResult($customer, $conversation->id(), $message->id());
    }

    /**
     * Captures a lead for the unknown contact and answers the customer it created (CaptureLead's nested dispatch has
     * flushed it, so the contact lookup sees it).
     *
     * @param 'phone'|'email' $kind
     */
    private function firstContact(string $dealershipId, Channel $channel, string $kind, string $from): string
    {
        $this->leads->captureFirstContact(
            'phone' === $kind ? $from : null,
            'email' === $kind ? $from : null,
            'Unknown',
            $from,
            Channel::Email === $channel ? 'First contact by email' : 'First contact by text',
        );

        return $this->contacts->customerWith($dealershipId, $kind, $from)
            ?? throw new \LogicException('The lead was captured in another dealership than the inbound message\'s.');
    }

    /**
     * @return array{0: 'phone'|'email', 1: string}
     */
    public static function contact(Channel $channel, string $raw): array
    {
        return match ($channel) {
            Channel::Text => ['phone', PhoneNumber::fromString($raw)->e164],
            Channel::Email => ['email', Email::fromString($raw)->value],
            Channel::Call => throw new InvalidValue('channel_not_receivable', 'Only texts and emails are received.', ['channel' => 'call']),
        };
    }
}
