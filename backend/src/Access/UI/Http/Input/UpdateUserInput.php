<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateUserInput
{
    public function __construct(
        #[Assert\Length(max: 80)]
        public ?string $firstName = null,
        #[Assert\Length(max: 80)]
        public ?string $lastName = null,
        public ?\App\Access\Domain\Model\Role $role = null,
    ) {
    }
}
