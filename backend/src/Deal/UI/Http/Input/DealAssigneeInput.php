<?php

namespace App\Deal\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class DealAssigneeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
    ) {
    }
}
