<?php

namespace App\Messaging\UI\Http\Output;

final readonly class ConversationDetailOutput
{
    /**
     * @param list<MessageOutput> $messages
     */
    public function __construct(
        public ConversationOutput $conversation,
        public array $messages,
        public ConversationContactsOutput $contacts,
    ) {
    }
}
