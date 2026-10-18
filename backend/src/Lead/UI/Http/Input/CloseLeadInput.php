<?php

namespace App\Lead\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CloseLeadInput
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $reason = null,
    ) {
    }
}
