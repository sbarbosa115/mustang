<?php

namespace App\DataImport\Application\Port;

/**
 * The people and settings of a dealership an import refers to.
 */
interface TeamMembers
{
    /**
     * The active user of the dealership with this email (any case).
     *
     * @return array{id: string, role: string}|null role is sales, bdc, manager or admin
     */
    public function activeMemberByEmail(string $dealershipId, string $email): ?array;

    public function timezoneOf(string $dealershipId): string;
}
