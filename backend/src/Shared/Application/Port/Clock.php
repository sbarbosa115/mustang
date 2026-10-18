<?php

namespace App\Shared\Application\Port;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
