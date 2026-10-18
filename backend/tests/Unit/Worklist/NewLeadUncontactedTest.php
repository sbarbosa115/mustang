<?php

namespace App\Tests\Unit\Worklist;

use App\Worklist\Domain\Model\LeadFacts;
use App\Worklist\Domain\Rule\NewLeadUncontacted;
use PHPUnit\Framework\TestCase;

final class NewLeadUncontactedTest extends TestCase
{
    public function testFiresForALeadNobodyContactedWithMinutesInTheScore(): void
    {
        $facts = Facts::customer(['lead' => new LeadFacts('l1', Facts::ago('25 minutes'), 'website', 'u1', false, '2024 Camry')]);

        $reason = (new NewLeadUncontacted())->evaluate($facts, Facts::now());

        self::assertNotNull($reason);
        self::assertSame('new_lead_uncontacted', $reason->code);
        self::assertSame(['minutes' => 25, 'source' => 'website', 'vehicle' => '2024 Camry'], $reason->params);
        self::assertSame(115, $reason->score, '90 plus the minutes since the lead came in.');
        self::assertFalse($reason->hot, 'Hot is kept for unanswered customers.');
        self::assertEquals(Facts::ago('25 minutes'), $reason->since);
    }

    public function testDoesNotFireOnceContactedOrWithoutALead(): void
    {
        $rule = new NewLeadUncontacted();

        self::assertNull($rule->evaluate(Facts::customer(['lead' => new LeadFacts('l1', Facts::ago('1 hour'), 'phone', 'u1', true)]), Facts::now()));
        self::assertNull($rule->evaluate(Facts::customer(), Facts::now()));
    }
}
