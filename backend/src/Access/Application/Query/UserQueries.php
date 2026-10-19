<?php

namespace App\Access\Application\Query;

use App\Access\Domain\Model\Dealership;
use App\Access\Domain\Model\User;

/**
 * Reads of the current dealership's users.
 */
interface UserQueries
{
    /**
     * @return array{items: list<User>, total: int}
     */
    public function page(int $offset, int $limit, ?string $term): array;

    /**
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function byId(string $id): User;

    public function currentDealership(): Dealership;

    /**
     * @param list<string> $userIds
     *
     * @return array<string, bool> user id => has a confirmed MFA credential
     */
    public function mfaEnrolled(array $userIds): array;

    /**
     * @param list<string> $userIds
     *
     * @return array<string, string> user id => "First Last", for showing assignees on other records
     */
    public function names(array $userIds): array;
}
