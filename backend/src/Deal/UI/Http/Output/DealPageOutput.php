<?php

namespace App\Deal\UI\Http\Output;

final readonly class DealPageOutput
{
    /**
     * @param list<DealOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
