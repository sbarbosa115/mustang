<?php

namespace App\Shared\Domain\Error;

class InvalidValue extends DomainError
{
    public function httpStatus(): int
    {
        return 422;
    }
}
