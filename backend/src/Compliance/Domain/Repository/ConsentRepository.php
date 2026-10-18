<?php

namespace App\Compliance\Domain\Repository;

use App\Compliance\Domain\Model\Consent;
use App\Shared\Domain\Channel;

interface ConsentRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Consent;

    /**
     * The same as get(), for a dealership named explicitly (the worker and the console have no signed-in user).
     *
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function getIn(string $dealershipId, string $id): Consent;

    /**
     * The granted records of one contact value on one channel in a dealership, whichever customer they were recorded on.
     *
     * @return list<Consent>
     */
    public function grantedFor(string $dealershipId, string $contactValue, Channel $channel): array;

    public function add(Consent $consent): void;
}
