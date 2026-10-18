<?php

namespace App\Messaging\Application\Command;

use App\Shared\Domain\Channel;

final readonly class CreateTemplate
{
    public function __construct(
        public string $name,
        public Channel $channel,
        public string $body,
        public ?string $subject = null,
        public bool $active = true,
    ) {
    }
}
