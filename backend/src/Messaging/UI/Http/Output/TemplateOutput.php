<?php

namespace App\Messaging\UI\Http\Output;

final readonly class TemplateOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public \App\Shared\Domain\Channel $channel,
        public string $body,
        public bool $active,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?string $subject = null,
    ) {
    }
}
