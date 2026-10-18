<?php

namespace App\Reporting\Application\Query;

use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Error\InvalidValue;

/**
 * The KPIs of a period and of the one before it. `from` and `to` are dates (inclusive) in the dealership's timezone;
 * left out, the period is the last 30 days ending today.
 */
final readonly class BuildKpiReport
{
    public const int DEFAULT_DAYS = 30;
    public const int MAX_DAYS = 366;

    public function __construct(private KpiQueries $queries, private Clock $clock)
    {
    }

    public function __invoke(string $dealershipId, ?string $from = null, ?string $to = null): KpiReport
    {
        $zone = new \DateTimeZone($this->queries->timezone($dealershipId));
        $today = $this->clock->now()->setTimezone($zone)->setTime(0, 0);
        $end = $this->date($to, 'to', $zone) ?? $today;
        $start = $this->date($from, 'from', $zone) ?? $end->modify(\sprintf('-%d days', self::DEFAULT_DAYS - 1));
        if ($start > $end) {
            throw new InvalidValue('invalid_period', '"from" is after "to".', ['field' => 'from']);
        }
        $period = new KpiPeriod($start, $end, $zone);
        if ($period->days() > self::MAX_DAYS) {
            throw new InvalidValue('invalid_period', \sprintf('A period is at most %d days.', self::MAX_DAYS), ['field' => 'to']);
        }
        $previous = $period->previous();

        return new KpiReport(
            $period,
            $previous,
            $this->queries->snapshot($dealershipId, $period->start(), $period->end()),
            $this->queries->snapshot($dealershipId, $previous->start(), $previous->end()),
        );
    }

    private function date(?string $value, string $field, \DateTimeZone $zone): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), $zone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidValue('invalid_period', \sprintf('"%s" is not a date (use yyyy-mm-dd).', $field), ['field' => $field]);
        }

        return $date;
    }
}
