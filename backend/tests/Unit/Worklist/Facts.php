<?php

namespace App\Tests\Unit\Worklist;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;

/**
 * Builders for the rule tests: a fixed "now" (Tuesday 20 October 2026, 14:00 in Tampa) and a customer with only the
 * facts a test names.
 */
final class Facts
{
    public const string NOW = '2026-10-20T18:00:00Z';

    public static function now(string $at = self::NOW, string $timezone = 'America/New_York'): Now
    {
        return new Now(new \DateTimeImmutable($at), $timezone);
    }

    public static function ago(string $modifier, string $from = self::NOW): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($from))->modify('-'.$modifier);
    }

    /**
     * @param array<string, mixed> $facts named arguments of CustomerFacts
     */
    public static function customer(array $facts = []): CustomerFacts
    {
        return new CustomerFacts(...['customerId' => 'c1', 'customerName' => 'Sarah Johnson', ...$facts]);
    }
}
