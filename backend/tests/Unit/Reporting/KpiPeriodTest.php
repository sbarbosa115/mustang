<?php

namespace App\Tests\Unit\Reporting;

use App\Reporting\Application\Query\KpiPeriod;
use PHPUnit\Framework\TestCase;

final class KpiPeriodTest extends TestCase
{
    private static function period(string $from, string $to, string $zone = 'America/New_York'): KpiPeriod
    {
        $z = new \DateTimeZone($zone);

        return new KpiPeriod(new \DateTimeImmutable($from, $z), new \DateTimeImmutable($to, $z), $z);
    }

    public function testDaysCountBothEnds(): void
    {
        self::assertSame(1, self::period('2025-03-08', '2025-03-08')->days());
        self::assertSame(30, self::period('2025-03-01', '2025-03-30')->days());
    }

    public function testDaysAreCalendarDaysAcrossADstChange(): void
    {
        self::assertSame(3, self::period('2025-03-08', '2025-03-10')->days(), 'The 71-hour span is three days.');
        self::assertSame(3, self::period('2025-11-01', '2025-11-03')->days(), 'The 73-hour span is three days.');
    }

    public function testInstantsComeFromLocalMidnights(): void
    {
        $spring = self::period('2025-03-08', '2025-03-10');
        self::assertSame('2025-03-08T05:00:00+00:00', $spring->start()->format('c'));
        self::assertSame('2025-03-11T04:00:00+00:00', $spring->end()->format('c'));

        $autumn = self::period('2025-11-01', '2025-11-03');
        self::assertSame('2025-11-01T04:00:00+00:00', $autumn->start()->format('c'), 'Midnight EDT.');
        self::assertSame('2025-11-04T05:00:00+00:00', $autumn->end()->format('c'), 'Midnight EST.');
    }

    public function testThePreviousPeriodEndsTheDayBefore(): void
    {
        $previous = self::period('2025-03-08', '2025-03-10')->previous();

        self::assertSame('2025-03-05', $previous->from->format('Y-m-d'));
        self::assertSame('2025-03-07', $previous->to->format('Y-m-d'));
        self::assertSame(3, $previous->days());
    }

    public function testThePreviousPeriodOfAMonthEndingInDstSpansTheSameDays(): void
    {
        $previous = self::period('2025-03-01', '2025-03-30')->previous();

        self::assertSame('2025-01-30', $previous->from->format('Y-m-d'));
        self::assertSame('2025-02-28', $previous->to->format('Y-m-d'));
        self::assertSame(30, $previous->days());
    }
}
