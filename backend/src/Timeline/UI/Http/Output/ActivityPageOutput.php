<?php

namespace App\Timeline\UI\Http\Output;

final readonly class ActivityPageOutput
{
    /**
     * @param list<ActivityOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
