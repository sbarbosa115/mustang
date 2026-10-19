<?php

namespace App\Lead\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CaptureLeadInput
{
    public function __construct(
        #[Assert\Uuid(strict: false)]
        public ?string $customerId = null,
        #[Assert\Length(max: 80)]
        public ?string $firstName = null,
        #[Assert\Length(max: 80)]
        public ?string $lastName = null,
        #[Assert\Length(max: 40)]
        public ?string $phone = null,
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,
        #[Assert\NotNull]
        public ?\App\Lead\Domain\Model\LeadSource $source = null,
        #[Assert\Length(max: 255)]
        public ?string $channelNote = null,
        #[Assert\Length(max: 255)]
        public ?string $vehicleInterestText = null,
        #[Assert\Uuid(strict: false)]
        public ?string $assignedUserId = null,
        public ?string $receivedAt = null,
    ) {
    }
}
