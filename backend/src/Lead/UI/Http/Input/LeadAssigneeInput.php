<?php

namespace App\Lead\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class LeadAssigneeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
    ) {
    }
}
