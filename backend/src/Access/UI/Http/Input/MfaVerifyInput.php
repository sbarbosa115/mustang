<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class MfaVerifyInput
{
    public function __construct(
        #[Assert\Length(max: 8)]
        public ?string $code = null,
        #[Assert\Length(max: 32)]
        public ?string $recoveryCode = null,
    ) {
    }
}
