<?php

namespace App\Messaging\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Simulates a customer's reply (dev and test only).
 */
final class DevInboundInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public ?string $from = null,
        public ?\App\Shared\Domain\Channel $channel = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        public ?string $body = null,
        #[Assert\Length(max: 255)]
        public ?string $subject = null,
    ) {
    }
}
