<?php

namespace App\Messaging\UI\Http;

use App\Messaging\Application\Port\CustomerContacts;
use App\Messaging\Application\Query\ConversationQueries;
use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Model\Message;
use App\Messaging\UI\Http\Output\ConversationContactsOutput;
use App\Messaging\UI\Http\Output\ConversationDetailOutput;
use App\Messaging\UI\Http\Output\ConversationOutput;
use App\Messaging\UI\Http\Output\MessageOutput;
use App\Messaging\UI\Http\Output\ProvenanceOutput;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\ConsentStates;
use App\Shared\Application\Port\CustomerDirectory;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\Output\ChannelConsentOutput;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * Builds inbox responses, loading names, consent and last messages for a whole page at once (no N+1).
 */
final readonly class ConversationPresenter
{
    public function __construct(
        private ConversationQueries $queries,
        private CustomerDirectory $customers,
        private UserDirectory $users,
        private ConsentStates $consents,
        private CustomerContacts $contacts,
        private Clock $clock,
    ) {
    }

    /**
     * @param list<Conversation> $conversations
     *
     * @return list<ConversationOutput>
     */
    public function list(array $conversations): array
    {
        $customerIds = array_values(array_unique(array_map(static fn (Conversation $c): string => $c->customerId(), $conversations)));
        $last = $this->queries->lastMessages(array_map(static fn (Conversation $c): string => $c->id(), $conversations));
        $customerNames = $this->customers->names($customerIds);
        $consent = $this->consents->forCustomers($customerIds);
        $userNames = $this->users->names([
            ...array_map(static fn (Conversation $c): ?string => $c->assignedUserId(), $conversations),
            ...array_map(static fn (Message $m): ?string => $m->sentByUserId(), array_values($last)),
        ]);
        $now = $this->clock->now();
        $minutes = $this->queries->fumbledAfterMinutes();

        return array_map(static function (Conversation $c) use ($last, $customerNames, $consent, $userNames, $now, $minutes): ConversationOutput {
            $state = $consent[$c->customerId()] ?? ['text' => 'missing', 'email' => 'missing', 'call' => 'missing'];
            $lastMessage = $last[$c->id()] ?? null;

            return new ConversationOutput(
                id: $c->id(),
                customer: new CustomerRefOutput($c->customerId(), $customerNames[$c->customerId()] ?? ''),
                unreadCount: $c->unreadCount(),
                unreplied: $c->isUnreplied(),
                fumbled: $c->isFumbled($now, $minutes),
                consent: new ChannelConsentOutput($state['text'], $state['email'], $state['call']),
                assignee: self::ref($c->assignedUserId(), $userNames),
                lastMessage: null === $lastMessage ? null : self::message($lastMessage, $userNames),
                lastMessageAt: $c->lastMessageAt(),
            );
        }, $conversations);
    }

    public function one(Conversation $conversation): ConversationOutput
    {
        return $this->list([$conversation])[0];
    }

    public function detail(Conversation $conversation): ConversationDetailOutput
    {
        $messages = $this->queries->messages($conversation->id());
        $names = $this->users->names(array_map(static fn (Message $m): ?string => $m->sentByUserId(), $messages));
        $contacts = $this->contacts->contactsOf($conversation->dealershipId(), $conversation->customerId());

        return new ConversationDetailOutput(
            $this->one($conversation),
            array_map(static fn (Message $m): MessageOutput => self::message($m, $names), $messages),
            new ConversationContactsOutput($contacts['phone'], $contacts['email']),
        );
    }

    public function messageOutput(Message $message): MessageOutput
    {
        return self::message($message, $this->users->names([$message->sentByUserId()]));
    }

    public function provenance(Message $message): ProvenanceOutput
    {
        /** @var array<string, mixed>|null $snapshot */
        $snapshot = $message->consentStateSnapshot();

        return new ProvenanceOutput(
            messageId: $message->id(),
            channel: $message->channel(),
            direction: $message->direction(),
            toValue: $message->toValue(),
            fromValue: $message->fromValue(),
            status: $message->status(),
            sentBy: self::ref($message->sentByUserId(), $this->users->names([$message->sentByUserId()])),
            sentAt: $message->sentAt(),
            receivedAt: $message->receivedAt(),
            templateId: $message->templateId(),
            consentId: $message->consentId(),
            consentStateSnapshot: $snapshot,
            transport: $message->transport(),
            transportMessageId: $message->transportMessageId(),
            failedReason: $message->failedReason(),
        );
    }

    /**
     * @param array<string, string> $userNames
     */
    private static function message(Message $m, array $userNames): MessageOutput
    {
        return new MessageOutput(
            id: $m->id(),
            conversationId: $m->conversationId(),
            direction: $m->direction(),
            channel: $m->channel(),
            body: $m->body(),
            status: $m->status(),
            toValue: $m->toValue(),
            fromValue: $m->fromValue(),
            createdAt: $m->createdAt(),
            subject: $m->subject(),
            sentBy: self::ref($m->sentByUserId(), $userNames),
            templateId: $m->templateId(),
            failedReason: $m->failedReason(),
            sentAt: $m->sentAt(),
            receivedAt: $m->receivedAt(),
        );
    }

    /**
     * @param array<string, string> $names
     */
    private static function ref(?string $userId, array $names): ?UserRefOutput
    {
        return null === $userId ? null : new UserRefOutput($userId, $names[$userId] ?? '');
    }
}
