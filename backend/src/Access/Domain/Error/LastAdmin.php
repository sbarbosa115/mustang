<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class LastAdmin extends Conflict
{
    public function __construct()
    {
        parent::__construct('last_admin', 'A dealership needs at least one active admin. Make someone else an admin first.');
    }
}
