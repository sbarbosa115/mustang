<?php

namespace App\Shared\UI\Http\Output;

/**
 * A customer shown on another record.
 */
final readonly class CustomerRefOutput
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
