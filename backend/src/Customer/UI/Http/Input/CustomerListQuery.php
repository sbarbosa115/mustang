<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CustomerListQuery
{
    public function __construct(
        #[Assert\Positive]
        public ?int $page = null,
        #[Assert\GreaterThanOrEqual(1)]
        #[Assert\LessThanOrEqual(100)]
        public ?int $perPage = null,
        #[Assert\Length(max: 120)]
        public ?string $q = null,
        #[Assert\Uuid(strict: false)]
        public ?string $assignedTo = null,
        public ?bool $archived = null,
    ) {
    }
}
