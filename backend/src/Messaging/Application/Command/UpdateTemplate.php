<?php

namespace App\Messaging\Application\Command;

final readonly class UpdateTemplate
{
    public function __construct(
        public string $templateId,
        public ?string $name = null,
        public ?string $body = null,
        public ?string $subject = null,
        public ?bool $active = null,
    ) {
    }
}
