<?php

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\CustomerNote;

interface CustomerNoteRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): CustomerNote;

    public function add(CustomerNote $customerNote): void;
}
