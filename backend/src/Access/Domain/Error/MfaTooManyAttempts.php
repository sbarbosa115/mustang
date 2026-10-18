<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\TooManyAttempts;

final class MfaTooManyAttempts extends TooManyAttempts
{
    public function __construct()
    {
        parent::__construct('mfa_too_many_attempts', 'Too many wrong codes. Wait a few minutes and try again.');
    }
}
