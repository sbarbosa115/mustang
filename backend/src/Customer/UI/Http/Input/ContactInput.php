<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactInput
{
    public function __construct(
        #[Assert\NotNull]
        public ?\App\Customer\Domain\Model\ContactKind $kind = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public ?string $value = null,
        #[Assert\Length(max: 40)]
        public ?string $label = null,
        public ?bool $isPrimary = null,
    ) {
    }
}
