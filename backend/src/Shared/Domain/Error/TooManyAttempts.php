<?php

namespace App\Shared\Domain\Error;

class TooManyAttempts extends DomainError
{
    public function httpStatus(): int
    {
        return 429;
    }
}
