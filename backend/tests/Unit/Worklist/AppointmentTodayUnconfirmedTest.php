<?php

namespace App\Tests\Unit\Worklist;

use App\Worklist\Domain\Model\AppointmentFacts;
use App\Worklist\Domain\Rule\AppointmentTodayUnconfirmed;
use PHPUnit\Framework\TestCase;

final class AppointmentTodayUnconfirmedTest extends TestCase
{
    public function testFiresForAnUnconfirmedAppointmentLaterToday(): void
    {
        $at = new \DateTimeImmutable('2026-10-20T21:30:00Z'); // 17:30 in Tampa
        $facts = Facts::customer(['appointmentToday' => new AppointmentFacts('a1', $at, 'scheduled', 'test_drive')]);

        $reason = (new AppointmentTodayUnconfirmed())->evaluate($facts, Facts::now());

        self::assertNotNull($reason);
        self::assertSame('appointment_today_unconfirmed', $reason->code);
        self::assertSame(['scheduled_at' => '2026-10-20T21:30:00+00:00', 'kind' => 'test_drive'], $reason->params);
        self::assertSame(80, $reason->score);
    }

    public function testTodayIsTheDealershipsDay(): void
    {
        $rule = new AppointmentTodayUnconfirmed();
        // 01:00 UTC on the 21st is still the 20th (21:00) in Tampa.
        $lateTonight = Facts::customer(['appointmentToday' => new AppointmentFacts('a1', new \DateTimeImmutable('2026-10-21T01:00:00Z'), 'scheduled', 'visit')]);
        // 05:00 UTC on the 21st is 01:00 tomorrow in Tampa.
        $tomorrow = Facts::customer(['appointmentToday' => new AppointmentFacts('a1', new \DateTimeImmutable('2026-10-21T05:00:00Z'), 'scheduled', 'visit')]);

        self::assertNotNull($rule->evaluate($lateTonight, Facts::now()), '21:00 local is today.');
        self::assertNull($rule->evaluate($tomorrow, Facts::now()), '01:00 local tomorrow is not today.');
    }

    public function testDoesNotFireOnceConfirmed(): void
    {
        $facts = Facts::customer(['appointmentToday' => new AppointmentFacts('a1', new \DateTimeImmutable('2026-10-20T21:30:00Z'), 'confirmed', 'visit')]);

        self::assertNull((new AppointmentTodayUnconfirmed())->evaluate($facts, Facts::now()));
        self::assertNull((new AppointmentTodayUnconfirmed())->evaluate(Facts::customer(), Facts::now()));
    }
}
