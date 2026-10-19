<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class EmailTaken extends Conflict
{
    public function __construct()
    {
        parent::__construct('email_taken', 'Another account already uses this email.');
    }
}
