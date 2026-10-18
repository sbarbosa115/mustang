<?php

namespace App\Access\Application\Mfa;

/**
 * An admin switches a user's factor off; the user enrolls again at their next sign-in.
 */
final readonly class ResetMfa
{
    public function __construct(public string $userId)
    {
    }
}
