<?php

namespace App\Customer\UI\Http\Output;

final readonly class ContactOutput
{
    public function __construct(
        public string $id,
        public \App\Customer\Domain\Model\ContactKind $kind,
        public string $value,
        public bool $isPrimary,
        public ?string $label = null,
    ) {
    }
}
