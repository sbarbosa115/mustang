<?php

namespace App\Messaging\Application\Port;

/**
 * The console has nobody signed in, but capturing a lead for an unknown contact runs the Lead and Customer commands,
 * which read the dealership from TenantContext. A simulated reply therefore runs as the dealership's first active
 * admin, the way DataImport's worker runs as the uploader.
 */
interface ActingUser
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     *
     * @throws \App\Shared\Domain\Error\NotAllowed no_active_admin when the dealership has no active admin
     */
    public function runAsAdminOf(string $dealershipId, callable $work): mixed;
}
