<?php

namespace App\Shared\Domain\Error;

class TooLarge extends DomainError
{
    public function httpStatus(): int
    {
        return 413;
    }
}
