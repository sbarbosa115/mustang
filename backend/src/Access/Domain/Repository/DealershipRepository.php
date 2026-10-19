<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\Dealership;

interface DealershipRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound
     */
    public function get(string $id): Dealership;

    public function add(Dealership $dealership): void;
}
