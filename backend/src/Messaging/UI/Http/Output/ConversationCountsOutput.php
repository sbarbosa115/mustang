<?php

namespace App\Messaging\UI\Http\Output;

use App\Shared\UI\Http\Output\UserCountOutput;

final readonly class ConversationCountsOutput
{
    /**
     * @param list<UserCountOutput> $byUser
     */
    public function __construct(
        public int $all,
        public int $unreplied,
        public int $fumbled,
        public array $byUser,
    ) {
    }
}
