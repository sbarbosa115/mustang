<?php

namespace App\DataImport\Application\Port;

/**
 * The worker has nobody signed in. An import does what its uploader could do through the screens, so it runs as the
 * uploader: the other contexts' commands then see the uploader's dealership and name them as the author.
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
     * @throws \App\Shared\Domain\Error\NotAllowed uploader_unavailable when the user is no longer an active member of
     *                                             the dealership
     */
    public function runAs(string $dealershipId, string $userId, callable $work): mixed;
}
