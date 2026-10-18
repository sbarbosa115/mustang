<?php

namespace App\Compliance\UI\Http\Output;

final readonly class SuppressionPageOutput
{
    /**
     * @param list<SuppressionOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
