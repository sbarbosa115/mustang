<?php

namespace App\Worklist\Domain\Model;

/**
 * The instant the list is computed for, and the dealership's timezone, which decides what "today" is.
 */
final readonly class Now
{
    public \DateTimeImmutable $at;

    public function __construct(\DateTimeImmutable $at, public string $timezone)
    {
        $this->at = $at->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * Midnight that starts today at the dealership, and the next one, in UTC.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    public function today(): array
    {
        $start = $this->at->setTimezone(new \DateTimeZone($this->timezone))->setTime(0, 0);
        $utc = new \DateTimeZone('UTC');

        return [$start->setTimezone($utc), $start->modify('+1 day')->setTimezone($utc)];
    }

    public function isToday(\DateTimeImmutable $instant): bool
    {
        [$start, $end] = $this->today();

        return $instant >= $start && $instant < $end;
    }

    /**
     * Whole minutes from $since to now (never negative).
     */
    public function minutesSince(\DateTimeImmutable $since): int
    {
        return max(0, intdiv($this->at->getTimestamp() - $since->getTimestamp(), 60));
    }

    /**
     * Seconds from $since to now (negative when $since is in the future).
     */
    public function secondsSince(\DateTimeImmutable $since): int
    {
        return $this->at->getTimestamp() - $since->getTimestamp();
    }
}
