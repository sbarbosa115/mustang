<?php

namespace App\Compliance\Application\Query;

use App\Shared\Domain\Channel;

/**
 * One suppressed contact, as listed on the opt-outs screen.
 */
final readonly class SuppressionView
{
    public function __construct(
        public string $id,
        public string $contactValue,
        public Channel $channel,
        public string $reason,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
