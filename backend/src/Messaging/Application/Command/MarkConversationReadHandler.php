<?php

namespace App\Messaging\Application\Command;

use App\Messaging\Domain\Repository\ConversationRepository;
use App\Shared\Application\Bus\CommandHandler;

final readonly class MarkConversationReadHandler implements CommandHandler
{
    public function __construct(private ConversationRepository $conversations)
    {
    }

    public function __invoke(MarkConversationRead $command): void
    {
        $this->conversations->get($command->conversationId)->markRead();
    }
}
