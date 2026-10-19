<?php

namespace App\Shared\Domain\Error;

class Conflict extends DomainError
{
    public function httpStatus(): int
    {
        return 409;
    }
}
