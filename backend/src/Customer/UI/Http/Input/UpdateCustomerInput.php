<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateCustomerInput
{
    public function __construct(
        #[Assert\Length(max: 80)]
        public ?string $firstName = null,
        #[Assert\Length(max: 80)]
        public ?string $lastName = null,
        public ?\App\Shared\Domain\Channel $preferredChannel = null,
    ) {
    }
}
