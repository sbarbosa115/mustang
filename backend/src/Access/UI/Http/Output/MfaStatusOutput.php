<?php

namespace App\Access\UI\Http\Output;

final readonly class MfaStatusOutput
{
    public function __construct(
        public bool $enrolled,
        public bool $verified,
        public int $recoveryCodesRemaining,
    ) {
    }
}
