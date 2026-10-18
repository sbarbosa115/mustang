<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateContactInput
{
    public function __construct(
        #[Assert\Length(max: 180)]
        public ?string $value = null,
        #[Assert\Length(max: 40)]
        public ?string $label = null,
        public ?bool $isPrimary = null,
    ) {
    }
}
