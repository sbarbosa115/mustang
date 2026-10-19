<?php

namespace App\Messaging\UI\Http\Output;

final readonly class ConversationContactsOutput
{
    public function __construct(
        public ?string $phone = null,
        public ?string $email = null,
    ) {
    }
}
