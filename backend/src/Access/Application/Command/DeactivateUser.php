<?php

namespace App\Access\Application\Command;

final readonly class DeactivateUser
{
    public function __construct(public string $userId)
    {
    }
}
