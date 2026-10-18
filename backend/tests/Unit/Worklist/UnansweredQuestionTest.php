<?php

namespace App\Tests\Unit\Worklist;

use App\Shared\Domain\Channel;
use App\Worklist\Domain\Model\ConversationFacts;
use App\Worklist\Domain\Rule\UnansweredQuestion;
use PHPUnit\Framework\TestCase;

final class UnansweredQuestionTest extends TestCase
{
    public function testAQuestionWaitingAddsTwentyAndSaysSo(): void
    {
        $facts = Facts::customer(['conversation' => new ConversationFacts(Facts::ago('47 minutes'), null, Channel::Text, questionUnanswered: true)]);

        $reason = (new UnansweredQuestion(30))->evaluate($facts, Facts::now());

        self::assertNotNull($reason);
        self::assertSame('unanswered_inbound', $reason->code, 'The same reason, worded with the "question unanswered" suffix.');
        self::assertSame(['minutes' => 47, 'channel' => 'text', 'question' => true], $reason->params);
        self::assertSame(167, $reason->score, '100 plus the minutes plus 20 for the question.');
        self::assertTrue($reason->hot);
    }

    public function testDoesNotFireWithoutAQuestionOrOnceAnswered(): void
    {
        $rule = new UnansweredQuestion(30);

        self::assertNull($rule->evaluate(Facts::customer(['conversation' => new ConversationFacts(Facts::ago('5 minutes'), null, Channel::Text)]), Facts::now()), 'No "?" in what the customer wrote.');
        self::assertNull($rule->evaluate(Facts::customer(['conversation' => new ConversationFacts(Facts::ago('2 hours'), Facts::ago('1 hour'), Channel::Text, questionUnanswered: true)]), Facts::now()), 'Answered since.');
    }
}
