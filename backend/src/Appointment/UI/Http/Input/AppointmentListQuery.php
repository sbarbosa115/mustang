<?php

namespace App\Appointment\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AppointmentListQuery
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        #[Assert\Uuid(strict: false)]
        public ?string $userId = null,
        #[Assert\Uuid(strict: false)]
        public ?string $customerId = null,
    ) {
    }
}
