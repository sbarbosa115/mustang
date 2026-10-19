<?php

namespace App\Deal\Application\Query;

use App\Deal\Domain\Model\DealStage;

/**
 * What a deal list is narrowed by; null means "any".
 */
final readonly class DealFilter
{
    public function __construct(
        public ?string $term = null,
        public ?DealStage $stage = null,
        public ?string $assignedTo = null,
        public ?string $customerId = null,
        public ?bool $open = null,
    ) {
    }
}
