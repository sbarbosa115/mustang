<?php

namespace App\Reporting\Application\Query;

/**
 * A run of whole days in the dealership's timezone, both ends inclusive. DST changes make a day 23 or 25 hours long,
 * so the instants are drawn from local midnights, never from adding hours.
 */
final readonly class KpiPeriod
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public \DateTimeZone $zone,
    ) {
    }

    /**
     * Midnight of the first day, as a UTC instant.
     */
    public function start(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->from->format('Y-m-d').' 00:00:00', $this->zone)->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * Midnight after the last day, as a UTC instant (exclusive).
     */
    public function end(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->to->modify('+1 day')->format('Y-m-d').' 00:00:00', $this->zone)->setTimezone(new \DateTimeZone('UTC'));
    }

    public function days(): int
    {
        return (int) $this->from->setTime(12, 0)->diff($this->to->setTime(12, 0))->days + 1;
    }

    /**
     * The period of the same number of days that ends the day before this one starts.
     */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->modify(\sprintf('-%d days', $days)), $this->from->modify('-1 day'), $this->zone);
    }
}
