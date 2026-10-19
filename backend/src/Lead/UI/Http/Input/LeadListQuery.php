<?php

namespace App\Lead\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class LeadListQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $page = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        public ?int $perPage = null,
        #[Assert\Length(max: 120)]
        public ?string $q = null,
        public ?\App\Lead\Domain\Model\LeadStatus $status = null,
        #[Assert\Uuid(strict: false)]
        public ?string $assignedTo = null,
        public ?\App\Lead\Domain\Model\LeadSource $source = null,
    ) {
    }
}
