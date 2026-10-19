<?php

namespace App\Compliance\Domain\Repository;

use App\Compliance\Domain\Model\Suppression;
use App\Shared\Domain\Channel;

/**
 * Contacts that opted out. Global: a STOP at one dealership blocks every dealership (FR-12).
 */
interface SuppressionList
{
    public function find(string $contactValue, Channel $channel): ?Suppression;

    public function add(Suppression $suppression): void;

    public function remove(Suppression $suppression): void;
}
