<?php

namespace App\Tests\Unit\Worklist;

use App\Shared\Domain\Channel;
use App\Worklist\Domain\Model\ConversationFacts;
use App\Worklist\Domain\Rule\UnansweredInbound;
use PHPUnit\Framework\TestCase;

final class UnansweredInboundTest extends TestCase
{
    public function testFiresWhenTheCustomerWroteLastWithMinutesWaitingInTheScore(): void
    {
        $facts = Facts::customer(['conversation' => new ConversationFacts(Facts::ago('12 minutes'), Facts::ago('1 hour'), Channel::Text)]);

        $reason = (new UnansweredInbound(30))->evaluate($facts, Facts::now());

        self::assertNotNull($reason, 'The customer wrote after the last reply.');
        self::assertSame('unanswered_inbound', $reason->code);
        self::assertSame(['minutes' => 12, 'channel' => 'text', 'question' => false], $reason->params);
        self::assertSame(112, $reason->score, '100 plus the minutes waiting.');
        self::assertFalse($reason->hot, 'Not hot until it waits more than 30 minutes.');
        self::assertEquals(Facts::ago('12 minutes'), $reason->since);
    }

    public function testIsHotBeyondTheFumbledMinutes(): void
    {
        $facts = Facts::customer(['conversation' => new ConversationFacts(Facts::ago('31 minutes'), null, Channel::Email)]);

        $reason = (new UnansweredInbound(30))->evaluate($facts, Facts::now());

        self::assertNotNull($reason);
        self::assertTrue($reason->hot, 'Unanswered for more than 30 minutes is hot.');
        self::assertSame(131, $reason->score);
        $exactly = (new UnansweredInbound(30))->evaluate(Facts::customer(['conversation' => new ConversationFacts(Facts::ago('30 minutes'), null)]), Facts::now());
        self::assertNotNull($exactly);
        self::assertFalse($exactly->hot, 'Exactly 30 minutes is not yet beyond.');
    }

    public function testDoesNotFireOnceSomeoneReplied(): void
    {
        $rule = new UnansweredInbound(30);

        self::assertNull($rule->evaluate(Facts::customer(['conversation' => new ConversationFacts(Facts::ago('2 hours'), Facts::ago('1 hour'))]), Facts::now()), 'The rep replied after the customer.');
        self::assertNull($rule->evaluate(Facts::customer(['conversation' => new ConversationFacts(null, Facts::ago('1 hour'))]), Facts::now()), 'The customer never wrote.');
        self::assertNull($rule->evaluate(Facts::customer(), Facts::now()), 'No conversation.');
    }
}
