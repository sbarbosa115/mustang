<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class MfaCodeInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 6)]
        #[Assert\Length(max: 8)]
        public ?string $code = null,
    ) {
    }
}
