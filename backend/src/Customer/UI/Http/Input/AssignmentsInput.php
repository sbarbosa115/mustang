<?php

namespace App\Customer\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AssignmentsInput
{
    public function __construct(
        #[Assert\Uuid(strict: false)]
        public ?string $sales1UserId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $sales2UserId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $bdcUserId = null,
    ) {
    }
}
