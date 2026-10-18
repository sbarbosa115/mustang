<?php

namespace App\Tests\Unit\Worklist;

use App\Worklist\Domain\Model\DealFacts;
use App\Worklist\Domain\Rule\DealIdle;
use App\Worklist\Domain\Rule\StageThresholds;
use PHPUnit\Framework\TestCase;

final class DealIdleTest extends TestCase
{
    public function testFiresBeyondTheStageThresholdWithTheDaysInTheScore(): void
    {
        $facts = Facts::customer(['deal' => new DealFacts('d1', 'engaged', Facts::ago('6 days'), Facts::ago('4 days'))]);

        $reason = (new DealIdle())->evaluate($facts, Facts::now());

        self::assertNotNull($reason, 'Engaged and idle 4 days: beyond its 3 days.');
        self::assertSame('deal_idle', $reason->code);
        self::assertSame(['days' => 4, 'stage' => 'engaged', 'threshold' => 3], $reason->params);
        self::assertSame(54, $reason->score, '50 plus the days idle.');
        self::assertEquals(Facts::ago('4 days'), $reason->since);
    }

    public function testEachStageHasItsThreshold(): void
    {
        $rule = new DealIdle();
        foreach (['engaged' => 3, 'appointment' => 2, 'visit' => 2, 'proposal' => 1] as $stage => $days) {
            self::assertSame($days, StageThresholds::days($stage), $stage.' waits '.$days.' days.');
        }
        self::assertNull(StageThresholds::days('sold'), 'A closed stage never goes idle.');

        self::assertNull($rule->evaluate(Facts::customer(['deal' => new DealFacts('d1', 'engaged', Facts::ago('3 days'), Facts::ago('3 days'))]), Facts::now()), 'Exactly 3 days is not beyond.');
        self::assertNotNull($rule->evaluate(Facts::customer(['deal' => new DealFacts('d1', 'proposal', Facts::ago('25 hours'), Facts::ago('25 hours'))]), Facts::now()), 'A proposal waits one day.');
        self::assertNull($rule->evaluate(Facts::customer(['deal' => new DealFacts('d1', 'visit', Facts::ago('5 days'), Facts::ago('1 day'))]), Facts::now()), 'Recent activity resets the idle time.');
        self::assertNull($rule->evaluate(Facts::customer(['deal' => new DealFacts('d1', 'new', Facts::ago('9 days'), Facts::ago('9 days'))]), Facts::now()), 'A new deal is the lead rule\'s business.');
        self::assertNull($rule->evaluate(Facts::customer(), Facts::now()));
    }
}
