<?php

namespace App\Shared\Application\Port;

/**
 * Who is acting and for which dealership. Every repository and query scopes by dealershipId().
 */
interface TenantContext
{
    /**
     * @throws \App\Shared\Domain\Error\NotAllowed when nobody is signed in
     */
    public function dealershipId(): string;

    public function userId(): ?string;

    public function hasRole(string $role): bool;
}
