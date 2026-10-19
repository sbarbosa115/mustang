<?php

namespace App\Tests\Functional\Reporting;

use App\Reporting\Application\Query\BuildKpiReport;
use App\Reporting\Application\Query\KpiReport;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Id;
use App\Tests\Support\ApiTestCase;

/**
 * The KPI figures on rows with fixed times, around the DST change of 2025-03-09 in America/New_York.
 */
final class KpiQueriesTest extends ApiTestCase
{
    use KpiFixtures;

    private function report(string $dealership, ?string $from, ?string $to): KpiReport
    {
        return static::getContainer()->get(BuildKpiReport::class)($dealership, $from, $to);
    }

    public function testEveryKpiOnAKnownScenario(): void
    {
        $d = $this->newDealership('Kpi One');
        $this->scenario($d);

        $now = $this->report($d, '2025-03-08', '2025-03-10')->current;

        self::assertSame(4, $now->leads, 'A, B, C and D; E is 00:30 local on the 11th and F 23:59 local on the 7th.');
        self::assertSame(2, $now->followed, 'A and B were answered within 24 hours; C after 25, D only by a failed attempt.');
        self::assertSame(2, $now->contacted, 'A and C: a reply after a sent message.');
        self::assertSame(1, $now->appointments, 'A booked; C cancelled.');
        self::assertSame(1, $now->shows);
        self::assertSame(1, $now->sales);
        self::assertSame(50.0, $now->leadsFollowed());
        self::assertSame(50.0, $now->leadToContact());
        self::assertSame(50.0, $now->contactToAppointment());
        self::assertSame(100.0, $now->appointmentToShow());
        self::assertSame(100.0, $now->showToSale());
        // Waits: A 10 min, B 30, C 1500 (25 h), G 20 (its second text is not a new wait); the median of 10, 20, 30, 1500.
        self::assertSame(25.0, $now->firstResponseMinutes);
        // Three delivered in the period over two active salespeople plus the manager who delivered one.
        self::assertSame(1.0, $now->unitsPerSalesperson);
    }

    public function testThePreviousPeriodIsTheSameNumberOfDaysBefore(): void
    {
        $d = $this->newDealership('Kpi Two');
        $this->scenario($d);

        $report = $this->report($d, '2025-03-08', '2025-03-10');

        self::assertSame('2025-03-05', $report->previous->from->format('Y-m-d'));
        self::assertSame('2025-03-07', $report->previous->to->format('Y-m-d'));
        $before = $report->before;
        self::assertSame(2, $before->leads, 'H, and F at 23:59 local on the 7th: 04:59Z is still EST, so it belongs here.');
        self::assertSame(0.0, $before->leadsFollowed(), 'A rate of 1 lead with no reply is 0%, not "no data".');
        self::assertNull($before->contactToAppointment(), 'Nobody was contacted: nothing to divide by.');
        self::assertNull($before->appointmentToShow());
        self::assertNull($before->showToSale());
        self::assertNull($before->firstResponseMinutes, 'No wait was answered.');
        self::assertSame(0.0, $before->unitsPerSalesperson, 'Two salespeople, nothing delivered.');
    }

    public function testTheDayBoundariesAreTheDealershipsMidnightsAcrossTheDstChange(): void
    {
        $d = $this->newDealership('Kpi Three');
        $this->scenario($d);

        $report = $this->report($d, '2025-03-08', '2025-03-10');

        self::assertSame('2025-03-08 05:00:00', $report->period->start()->format('Y-m-d H:i:s'), 'Midnight EST.');
        self::assertSame('2025-03-11 04:00:00', $report->period->end()->format('Y-m-d H:i:s'), 'Midnight EDT: 71 hours later, not 72.');
        self::assertSame('2025-03-08 05:00:00', $report->previous->end()->format('Y-m-d H:i:s'), 'The previous period ends where this one starts.');
        self::assertSame('2025-03-05 05:00:00', $report->previous->start()->format('Y-m-d H:i:s'));
    }

    public function testAZoneAheadOfUtcDrawsItsOwnDays(): void
    {
        $d = $this->newDealership('Kpi Tokyo', 'Asia/Tokyo');
        $this->lead($d, Id::new(), '2025-03-09 14:59:00'); // 23:59 on the 9th in Tokyo
        $this->lead($d, Id::new(), '2025-03-09 15:00:00'); // 00:00 on the 10th

        self::assertSame(1, $this->report($d, '2025-03-09', '2025-03-09')->current->leads);
        self::assertSame(1, $this->report($d, '2025-03-10', '2025-03-10')->current->leads);
    }

    public function testAnEmptyPeriodHasNoRatesAndZeroCounts(): void
    {
        $d = $this->newDealership('Kpi Empty');

        $now = $this->report($d, '2025-03-08', '2025-03-10')->current;

        self::assertSame(0, $now->leads);
        self::assertNull($now->leadsFollowed());
        self::assertNull($now->leadToContact());
        self::assertNull($now->contactToAppointment());
        self::assertNull($now->appointmentToShow());
        self::assertNull($now->showToSale());
        self::assertNull($now->firstResponseMinutes);
        self::assertNull($now->unitsPerSalesperson, 'No salespeople to divide by.');
    }

    public function testAnotherDealershipsRowsAreNeverCounted(): void
    {
        $mine = $this->newDealership('Kpi Mine');
        $other = $this->newDealership('Kpi Theirs');
        $this->scenario($other);

        self::assertSame(0, $this->report($mine, '2025-03-08', '2025-03-10')->current->leads);
        self::assertSame(4, $this->report($other, '2025-03-08', '2025-03-10')->current->leads);
    }

    public function testASaleBeforeTheLeadDoesNotCountForIt(): void
    {
        $d = $this->newDealership('Kpi Old Sale');
        $sales = $this->newUser($d, 'sales');
        $c = Id::new();
        $this->lead($d, $c, '2025-03-08 14:00:00');
        $this->outbound($d, $c, '2025-03-08 14:10:00');
        $this->inbound($d, $c, '2025-03-08 15:00:00');
        $this->appointment($d, $c, $sales, 'shown', '2025-03-08 15:30:00');
        $this->deal($d, $c, $sales, soldAt: '2025-02-01 10:00:00');

        $now = $this->report($d, '2025-03-08', '2025-03-08')->current;

        self::assertSame(1, $now->shows);
        self::assertSame(0, $now->sales, 'The customer bought months before this lead.');
        self::assertSame(0.0, $now->showToSale());
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function badPeriods(): iterable
    {
        yield 'not a date' => ['yesterday', null];
        yield 'impossible day' => ['2025-02-30', '2025-03-01'];
        yield 'from after to' => ['2025-03-10', '2025-03-01'];
        yield 'too long' => ['2020-01-01', '2025-01-01'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badPeriods')]
    public function testABadPeriodIsRefused(?string $from, ?string $to): void
    {
        $this->expectException(InvalidValue::class);

        $this->report($this->newDealership('Kpi Bad'), $from, $to);
    }
}
