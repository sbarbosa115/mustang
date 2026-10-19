<?php

namespace App\Lead\Application\Command;

final readonly class UpdateRouting
{
    /**
     * @param list<string> $userIds the round-robin order
     */
    public function __construct(public array $userIds)
    {
    }
}
