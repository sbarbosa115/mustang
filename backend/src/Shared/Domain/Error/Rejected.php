<?php

namespace App\Shared\Domain\Error;

class Rejected extends DomainError
{
    public function httpStatus(): int
    {
        return 400;
    }
}
