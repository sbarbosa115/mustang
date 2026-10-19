<?php

namespace App\Audit\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AuditQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $page = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        public ?int $perPage = null,
        #[Assert\Length(max: 80)]
        public ?string $entityType = null,
        #[Assert\Length(max: 64)]
        public ?string $entityId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
