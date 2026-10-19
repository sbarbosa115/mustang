<?php

namespace App\Worklist\Domain\Model;

/**
 * Why a customer (or a deal) needs someone now. The UI words it from the code and its params.
 */
final readonly class Reason
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        public string $code,
        public array $params,
        public int $score,
        public bool $hot = false,
        public ?\DateTimeImmutable $since = null,
    ) {
    }
}
