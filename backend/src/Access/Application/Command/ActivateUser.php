<?php

namespace App\Access\Application\Command;

final readonly class ActivateUser
{
    public function __construct(public string $userId)
    {
    }
}
