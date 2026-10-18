<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Model\Role;

final readonly class ChangeRole
{
    public function __construct(public string $userId, public Role $role)
    {
    }
}
