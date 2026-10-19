<?php

namespace App\Deal\Application\Command;

/**
 * Give a deal to a rep ("Assign to me" from the intervention queue).
 */
final readonly class AssignDeal
{
    public function __construct(
        public string $dealId,
        public string $userId,
    ) {
    }
}
