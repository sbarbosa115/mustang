<?php

namespace App\Deal\Domain\Repository;

use App\Deal\Domain\Model\Deal;

interface DealRepository
{
    /**
     * @throws \App\Shared\Domain\Error\NotFound for a missing id or another dealership's record
     */
    public function get(string $id): Deal;

    public function add(Deal $deal): void;
}
