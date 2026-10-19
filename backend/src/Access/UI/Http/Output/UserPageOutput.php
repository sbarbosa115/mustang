<?php

namespace App\Access\UI\Http\Output;

final readonly class UserPageOutput
{
    /**
     * @param list<UserOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
