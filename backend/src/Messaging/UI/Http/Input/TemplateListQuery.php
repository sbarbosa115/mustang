<?php

namespace App\Messaging\UI\Http\Input;

final class TemplateListQuery
{
    public function __construct(
        public ?\App\Shared\Domain\Channel $channel = null,
        public ?bool $active = null,
    ) {
    }
}
