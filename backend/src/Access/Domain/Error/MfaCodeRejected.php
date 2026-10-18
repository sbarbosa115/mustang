<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/**
 * A code that does not prove the factor: wrong, expired, already used, or a spent recovery code.
 */
final class MfaCodeRejected extends InvalidValue
{
    public static function invalid(): self
    {
        return new self('mfa_invalid_code', 'That code is not right.');
    }

    public static function reused(): self
    {
        return new self('mfa_code_reused', 'That code was already used. Wait for the next one.');
    }
}
