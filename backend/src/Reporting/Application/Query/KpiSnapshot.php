<?php

namespace App\Reporting\Application\Query;

/**
 * The raw counts and the figures derived from them for one period. A rate whose denominator is 0 is null, never 0.
 */
final readonly class KpiSnapshot
{
    public function __construct(
        public int $leads,
        public int $contacted,
        public int $appointments,
        public int $shows,
        public int $sales,
        public int $followed,
        public ?float $firstResponseMinutes,
        public ?float $unitsPerSalesperson,
    ) {
    }

    public function leadsFollowed(): ?float
    {
        return self::percent($this->followed, $this->leads);
    }

    public function leadToContact(): ?float
    {
        return self::percent($this->contacted, $this->leads);
    }

    public function contactToAppointment(): ?float
    {
        return self::percent($this->appointments, $this->contacted);
    }

    public function appointmentToShow(): ?float
    {
        return self::percent($this->shows, $this->appointments);
    }

    public function showToSale(): ?float
    {
        return self::percent($this->sales, $this->shows);
    }

    private static function percent(int $part, int $whole): ?float
    {
        return 0 === $whole ? null : round($part * 100 / $whole, 1);
    }
}
