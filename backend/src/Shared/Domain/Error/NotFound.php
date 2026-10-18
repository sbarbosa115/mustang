<?php

namespace App\Shared\Domain\Error;

class NotFound extends DomainError
{
    public function httpStatus(): int
    {
        return 404;
    }
}
