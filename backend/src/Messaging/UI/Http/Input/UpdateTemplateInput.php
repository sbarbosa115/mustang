<?php

namespace App\Messaging\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTemplateInput
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public ?string $name = null,
        #[Assert\Length(max: 255)]
        public ?string $subject = null,
        #[Assert\Length(max: 5000)]
        public ?string $body = null,
        public ?bool $active = null,
    ) {
    }
}
