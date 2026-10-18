<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\Conflict;

final class MfaNotEnrolled extends Conflict
{
    public function __construct()
    {
        parent::__construct('mfa_not_enrolled', 'Set up two-step sign-in first.');
    }
}
