<?php

namespace App\Audit\UI\Http\Output;

final readonly class AuditPageOutput
{
    /**
     * @param list<AuditEntryOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
