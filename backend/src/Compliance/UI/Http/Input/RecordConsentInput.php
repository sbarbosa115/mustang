<?php

namespace App\Compliance\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class RecordConsentInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public ?string $contactValue = null,
        #[Assert\NotNull]
        public ?\App\Shared\Domain\Channel $channel = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 60)]
        public ?string $source = null,
        #[Assert\Length(max: 120)]
        public ?string $capturedWhere = null,
        #[Assert\Length(max: 2000)]
        public ?string $wording = null,
        #[Assert\Length(max: 120)]
        public ?string $campaign = null,
        public ?string $capturedAt = null,
    ) {
    }
}
