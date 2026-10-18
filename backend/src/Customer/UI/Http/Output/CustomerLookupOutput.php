<?php

namespace App\Customer\UI\Http\Output;

/**
 * Customers whose phone or email matches, for de-duplication while typing.
 */
final readonly class CustomerLookupOutput
{
    /**
     * @param list<CustomerListItemOutput> $matches
     */
    public function __construct(
        public array $matches,
    ) {
    }
}
