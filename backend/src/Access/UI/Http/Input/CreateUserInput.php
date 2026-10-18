<?php

namespace App\Access\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateUserInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public ?string $firstName = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public ?string $lastName = null,
        #[Assert\NotNull]
        public ?\App\Access\Domain\Model\Role $role = null,
        #[Assert\NotBlank]
        #[Assert\Length(min: 12)]
        #[Assert\Length(max: 200)]
        public ?string $password = null,
    ) {
    }
}
