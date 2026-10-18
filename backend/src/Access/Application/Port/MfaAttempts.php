<?php

namespace App\Access\Application\Port;

/**
 * Counts code attempts per user, so six digits cannot be guessed. Kept outside the command's transaction on purpose:
 * a refused code rolls back, its attempt must not.
 */
interface MfaAttempts
{
    /** Counts one attempt; false when the user has used up their attempts for now. */
    public function attempt(string $userId): bool;

    /** A correct code clears the count. */
    public function clear(string $userId): void;
}
