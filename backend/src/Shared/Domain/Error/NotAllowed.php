<?php

namespace App\Shared\Domain\Error;

class NotAllowed extends DomainError
{
    public function httpStatus(): int
    {
        return 403;
    }
}
