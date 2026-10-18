<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class CannotDeactivateSelf extends Conflict
{
    public function __construct()
    {
        parent::__construct('cannot_deactivate_self', 'You cannot deactivate your own account.');
    }
}
