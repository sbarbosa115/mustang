<?php

namespace App\Tests\Unit\Worklist;

use App\Worklist\Domain\Model\AppointmentFacts;
use App\Worklist\Domain\Rule\NoShowWithoutFollowUp;
use PHPUnit\Framework\TestCase;

final class NoShowWithoutFollowUpTest extends TestCase
{
    public function testFiresForANoShowInTheLastDayNobodyFollowedUp(): void
    {
        $facts = Facts::customer(['lastNoShow' => new AppointmentFacts('a1', Facts::ago('5 hours'), 'no_show', 'visit')]);

        $reason = (new NoShowWithoutFollowUp())->evaluate($facts, Facts::now());

        self::assertNotNull($reason);
        self::assertSame('no_show_without_follow_up', $reason->code);
        self::assertSame(['scheduled_at' => '2026-10-20T13:00:00+00:00', 'kind' => 'visit'], $reason->params);
        self::assertSame(70, $reason->score);
        self::assertEquals(Facts::ago('5 hours'), $reason->since);
    }

    public function testDoesNotFireAfterAFollowUpOrAfterADay(): void
    {
        $rule = new NoShowWithoutFollowUp();

        self::assertNull($rule->evaluate(Facts::customer(['lastNoShow' => new AppointmentFacts('a1', Facts::ago('5 hours'), 'no_show', 'visit', followedUp: true)]), Facts::now()), 'Someone wrote or rebooked.');
        self::assertNull($rule->evaluate(Facts::customer(['lastNoShow' => new AppointmentFacts('a1', Facts::ago('25 hours'), 'no_show', 'visit')]), Facts::now()), 'Older than 24 hours: the manager queue takes it.');
        self::assertNull($rule->evaluate(Facts::customer(), Facts::now()));
    }
}
