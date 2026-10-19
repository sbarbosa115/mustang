<?php

namespace App\Lead\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

/**
 * The round-robin order for new leads.
 */
final readonly class RoutingOutput
{
    /**
     * @param list<UserRefOutput> $users
     */
    public function __construct(
        public array $users,
        public ?UserRefOutput $next = null,
    ) {
    }
}
