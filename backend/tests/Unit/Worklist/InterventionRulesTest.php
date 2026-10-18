<?php

namespace App\Tests\Unit\Worklist;

use App\Shared\Domain\Channel;
use App\Worklist\Domain\Model\AppointmentFacts;
use App\Worklist\Domain\Model\ConversationFacts;
use App\Worklist\Domain\Model\DealFacts;
use App\Worklist\Domain\Model\LeadFacts;
use App\Worklist\Domain\Rule\InterventionRules;
use PHPUnit\Framework\TestCase;

final class InterventionRulesTest extends TestCase
{
    private static function deal(string $stage = 'engaged', string $idle = '1 hour'): DealFacts
    {
        return new DealFacts('d1', $stage, Facts::ago($idle), Facts::ago($idle), 'u1');
    }

    public function testACustomerUnansweredForMoreThanAnHour(): void
    {
        $rules = new InterventionRules();
        $waiting = static fn (string $ago) => Facts::customer(['deal' => self::deal(), 'conversation' => new ConversationFacts(Facts::ago($ago), null, Channel::Text)]);

        $reason = $rules->evaluate($waiting('61 minutes'), Facts::now());
        self::assertSame('customer_unanswered', $reason?->code);
        self::assertSame(['minutes' => 61], $reason->params);
        self::assertNull($rules->evaluate($waiting('60 minutes'), Facts::now()), 'An hour is still the rep\'s to answer.');
    }

    public function testADealIdleBeyondTwiceItsThreshold(): void
    {
        $rules = new InterventionRules();

        $reason = $rules->evaluate(Facts::customer(['deal' => self::deal('engaged', '7 days')]), Facts::now());
        self::assertSame('deal_idle', $reason?->code);
        self::assertSame(['days' => 7, 'stage' => 'engaged', 'threshold' => 6], $reason->params);
        self::assertNull($rules->evaluate(Facts::customer(['deal' => self::deal('engaged', '5 days')]), Facts::now()), 'Idle beyond 3 days is the rep\'s list; beyond 6 the manager\'s.');
    }

    public function testANoShowWithoutFollowUpForADay(): void
    {
        $rules = new InterventionRules();
        $noShow = static fn (string $ago, bool $followed = false) => Facts::customer(['deal' => self::deal(), 'lastNoShow' => new AppointmentFacts('a1', Facts::ago($ago), 'no_show', 'visit', $followed)]);

        $reason = $rules->evaluate($noShow('26 hours'), Facts::now());
        self::assertNotNull($reason);
        self::assertSame('no_show_without_follow_up', $reason->code);
        self::assertSame(26, $reason->params['hours']);
        self::assertNull($rules->evaluate($noShow('20 hours'), Facts::now()));
        self::assertNull($rules->evaluate($noShow('26 hours', true), Facts::now()));
    }

    public function testALeadUnassignedOrUncontactedForTwoHours(): void
    {
        $rules = new InterventionRules();
        $lead = static fn (string $ago, ?string $assignee, bool $contacted) => Facts::customer(['deal' => self::deal(), 'lead' => new LeadFacts('l1', Facts::ago($ago), 'website', $assignee, $contacted)]);

        self::assertSame(['minutes' => 150, 'unassigned' => true], $rules->evaluate($lead('150 minutes', null, true), Facts::now())?->params);
        self::assertSame(['minutes' => 120, 'unassigned' => false], $rules->evaluate($lead('2 hours', 'u1', false), Facts::now())?->params);
        self::assertNull($rules->evaluate($lead('3 hours', 'u1', true), Facts::now()), 'Assigned and contacted.');
        self::assertNull($rules->evaluate($lead('1 hour', null, false), Facts::now()), 'Not two hours yet.');
    }

    public function testOneReasonPerDealTheMostUrgentFirstAndNoDealNoRow(): void
    {
        $rules = new InterventionRules();
        $both = Facts::customer([
            'deal' => self::deal('engaged', '9 days'),
            'conversation' => new ConversationFacts(Facts::ago('2 hours'), null, Channel::Text),
        ]);

        self::assertSame('customer_unanswered', $rules->evaluate($both, Facts::now())?->code, 'An unanswered customer outranks an idle deal.');
        self::assertNull($rules->evaluate(Facts::customer(['conversation' => new ConversationFacts(Facts::ago('2 hours'), null)]), Facts::now()), 'The queue lists deals.');
    }
}
