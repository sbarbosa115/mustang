<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class MfaAlreadyEnrolled extends Conflict
{
    public function __construct()
    {
        parent::__construct('mfa_already_enrolled', 'Two-step sign-in is already set up. An admin can reset it.');
    }
}
