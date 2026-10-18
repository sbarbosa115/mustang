<?php

namespace App\Messaging\UI\Http\Output;

final readonly class ConversationPageOutput
{
    /**
     * @param list<ConversationOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
