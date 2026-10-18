<?php

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Application\Port\Clock;
use Psr\Clock\ClockInterface;

/**
 * Reads Symfony's clock, so tests can freeze time with MockClock.
 */
final readonly class SystemClock implements Clock
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
