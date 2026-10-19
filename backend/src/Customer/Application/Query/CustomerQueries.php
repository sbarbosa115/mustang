<?php

namespace App\Customer\Application\Query;

use App\Customer\Domain\Model\Customer;

/**
 * Reads of the current dealership's customers.
 */
interface CustomerQueries
{
    /**
     * @return array{items: list<Customer>, total: int}
     */
    public function page(int $offset, int $limit, ?string $term, ?string $assignedTo = null, bool $archived = false): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's customer
     */
    public function byId(string $id): Customer;

    /**
     * Customers with this phone (E.164) or email (lowercase); either may be null.
     *
     * @return list<Customer>
     */
    public function findByContact(?string $phone, ?string $email): array;

    /**
     * @param list<string> $customerIds
     *
     * @return array<string, array{id: string, stage: string, stage_entered_at: \DateTimeImmutable, vehicle_text: ?string}>
     */
    public function openDeals(array $customerIds): array;
}
