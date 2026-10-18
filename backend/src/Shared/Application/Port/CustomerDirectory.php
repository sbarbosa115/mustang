<?php

namespace App\Shared\Application\Port;

/**
 * Names of the current dealership's customers, for showing a customer on any record.
 */
interface CustomerDirectory
{
    /**
     * @param list<string|null> $customerIds
     *
     * @return array<string, string> customer id => "First Last"
     */
    public function names(array $customerIds): array;
}
