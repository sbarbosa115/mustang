<?php

namespace App\Access\UI\Http\Output;

/**
 * Shown once, right after enrollment.
 */
final readonly class MfaRecoveryCodesOutput
{
    /**
     * @param list<string> $recoveryCodes
     */
    public function __construct(
        public array $recoveryCodes,
    ) {
    }
}
