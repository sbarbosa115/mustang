<?php

namespace App\Deal\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class DealListQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $page = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        public ?int $perPage = null,
        #[Assert\Length(max: 120)]
        public ?string $q = null,
        public ?\App\Deal\Domain\Model\DealStage $stage = null,
        #[Assert\Uuid(strict: false)]
        public ?string $assignedTo = null,
        #[Assert\Uuid(strict: false)]
        public ?string $customerId = null,
        public ?bool $open = null,
    ) {
    }
}
