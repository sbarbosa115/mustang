<?php

namespace App\Compliance\Domain\Repository;

use App\Compliance\Domain\Model\Consent;

interface ConsentRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Consent;

    public function add(Consent $consent): void;
}
