<?php

namespace App\Messaging\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class TemplateInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $name = null,
        #[Assert\NotNull]
        public ?\App\Shared\Domain\Channel $channel = null,
        #[Assert\Length(max: 255)]
        public ?string $subject = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        public ?string $body = null,
        public ?bool $active = null,
    ) {
    }
}
