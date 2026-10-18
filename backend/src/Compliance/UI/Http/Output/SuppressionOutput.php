<?php

namespace App\Compliance\UI\Http\Output;

final readonly class SuppressionOutput
{
    public function __construct(
        public string $id,
        public string $contactValue,
        public \App\Shared\Domain\Channel $channel,
        public string $reason,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
