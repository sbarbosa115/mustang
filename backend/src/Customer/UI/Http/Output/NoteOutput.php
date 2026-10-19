<?php

namespace App\Customer\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;

final readonly class NoteOutput
{
    public function __construct(
        public string $id,
        public string $body,
        public \DateTimeImmutable $createdAt,
        public ?UserRefOutput $author = null,
    ) {
    }
}
