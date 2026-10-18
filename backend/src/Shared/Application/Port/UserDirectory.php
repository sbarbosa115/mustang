<?php

namespace App\Shared\Application\Port;

/**
 * Names of the current dealership's users, for showing an assignee or author on any record.
 */
interface UserDirectory
{
    /**
     * @param list<string|null> $userIds
     *
     * @return array<string, string> user id => "First Last"
     */
    public function names(array $userIds): array;
}
