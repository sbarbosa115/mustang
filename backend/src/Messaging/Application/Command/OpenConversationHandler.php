<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Application\Port\CustomerContacts;
use App\Messaging\Domain\Model\Conversation;
use App\Messaging\Domain\Repository\ConversationRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\NotFound;

final readonly class OpenConversationHandler implements CommandHandler
{
    public function __construct(private ConversationRepository $conversations, private CustomerContacts $contacts)
    {
    }

    /**
     * @return string the conversation id
     */
    public function __invoke(OpenConversation $command): string
    {
        $existing = $this->conversations->findByCustomer($command->dealershipId, $command->customerId);
        if (null !== $existing) {
            return $existing->id();
        }
        if (!$this->contacts->exists($command->dealershipId, $command->customerId)) {
            throw new NotFound('customer_not_found', 'The record does not exist.', ['id' => $command->customerId]);
        }
        $conversation = new Conversation(
            $command->dealershipId,
            $command->customerId,
            $this->contacts->defaultAssignee($command->dealershipId, $command->customerId),
        );
        $this->conversations->add($conversation);

        return $conversation->id();
    }
}
