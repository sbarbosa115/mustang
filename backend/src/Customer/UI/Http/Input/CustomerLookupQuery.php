<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CustomerLookupQuery
{
    public function __construct(
        #[Assert\Length(max: 40)]
        public ?string $phone = null,
        #[Assert\Length(max: 180)]
        public ?string $email = null,
    ) {
    }
}
