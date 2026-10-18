<?php

namespace App\Shared\UI\Http\Output;

/**
 * A user shown on another record (an assignee, an author).
 */
final readonly class UserRefOutput
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
