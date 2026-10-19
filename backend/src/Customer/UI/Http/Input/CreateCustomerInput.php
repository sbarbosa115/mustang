<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateCustomerInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public ?string $firstName = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public ?string $lastName = null,
        #[Assert\Length(max: 40)]
        public ?string $phone = null,
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        public ?\App\Shared\Domain\Channel $preferredChannel = null,
        #[Assert\Uuid(strict: false)]
        public ?string $sales1UserId = null,
    ) {
    }
}
