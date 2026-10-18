<?php

namespace App\DataImport\UI\Http\Output;

final readonly class ImportJobPageOutput
{
    /**
     * @param list<ImportJobOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
