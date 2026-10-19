<?php

namespace App\Customer\Domain\Repository;

use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;

interface CustomerRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Customer;

    public function add(Customer $customer): void;

    /**
     * The customer of this dealership that has this contact (value already normalised), other than $exceptCustomerId.
     */
    public function findByContact(ContactKind $kind, string $value, ?string $exceptCustomerId = null): ?Customer;
}
