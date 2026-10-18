<?php

namespace App\Lead\UI\Http\Output;

final readonly class LeadPageOutput
{
    /**
     * @param list<LeadOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
