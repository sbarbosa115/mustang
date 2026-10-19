<?php

namespace App\Shared\UI\Http\Output;

/**
 * A number per user, e.g. unanswered conversations per rep.
 */
final readonly class UserCountOutput
{
    public function __construct(
        public UserRefOutput $user,
        public int $count,
    ) {
    }
}
