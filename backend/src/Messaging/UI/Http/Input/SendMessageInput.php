<?php

namespace App\Messaging\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class SendMessageInput
{
    public function __construct(
        #[Assert\NotNull]
        public ?\App\Shared\Domain\Channel $channel = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        public ?string $body = null,
        #[Assert\Length(max: 255)]
        public ?string $subject = null,
        #[Assert\Uuid(strict: false)]
        public ?string $templateId = null,
    ) {
    }
}
