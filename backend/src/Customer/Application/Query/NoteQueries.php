<?php

namespace App\Customer\Application\Query;

use App\Customer\Domain\Model\CustomerNote;

/**
 * Reads of the notes on the current dealership's customers.
 */
interface NoteQueries
{
    /**
     * @return list<CustomerNote> newest first
     */
    public function forCustomer(string $customerId): array;
}
