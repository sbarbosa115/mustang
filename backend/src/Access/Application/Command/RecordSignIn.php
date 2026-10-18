<?php

namespace App\Access\Application\Command;

final readonly class RecordSignIn
{
    public function __construct(public string $userId)
    {
    }
}
