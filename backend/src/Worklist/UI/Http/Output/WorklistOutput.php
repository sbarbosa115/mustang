<?php

namespace App\Worklist\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class WorklistOutput
{
    /**
     * @param list<WorklistItemOutput> $items
     */
    public function __construct(
        public UserRefOutput $user,
        public array $items,
        public int $total,
    ) {
    }
}
