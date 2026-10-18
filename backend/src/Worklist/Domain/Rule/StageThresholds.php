<?php

namespace App\Worklist\Domain\Rule;

/**
 * How long a deal may sit in a stage before it counts as idle. The manager queue takes it at twice that.
 */
final class StageThresholds
{
    /** Days per stage; the stages not listed are never idle (new is the lead rule's, the rest are closed). */
    public const array DAYS = ['engaged' => 3, 'appointment' => 2, 'visit' => 2, 'proposal' => 1];

    public static function days(string $stage): ?int
    {
        return self::DAYS[$stage] ?? null;
    }
}
