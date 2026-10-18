<?php

namespace App\Customer\UI\Http\Output;

final readonly class CustomerPageOutput
{
    /**
     * @param list<CustomerListItemOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
