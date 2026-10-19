<?php

namespace App\Compliance\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RevokeConsentInput
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $reason = null,
    ) {
    }
}
