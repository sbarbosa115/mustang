<?php

namespace App\Tests\Unit\Worklist;

use App\Shared\Domain\Channel;
use App\Worklist\Domain\Model\AppointmentFacts;
use App\Worklist\Domain\Model\ConversationFacts;
use App\Worklist\Domain\Model\DealFacts;
use App\Worklist\Domain\Model\LeadFacts;
use App\Worklist\Infrastructure\Prioritizer\RuleBasedPrioritizer;
use PHPUnit\Framework\TestCase;

final class PrioritizerTest extends TestCase
{
    private const array GRANTED_TEXT = ['text' => 'granted', 'email' => 'granted', 'call' => 'missing'];

    public function testOneReasonPerCustomerAndTheHighestScoreWins(): void
    {
        $customer = Facts::customer([
            'consent' => self::GRANTED_TEXT,
            'conversation' => new ConversationFacts(Facts::ago('10 minutes'), null, Channel::Text, questionUnanswered: true),
            'lead' => new LeadFacts('l1', Facts::ago('15 minutes'), 'website', 'u1', false),
            'appointmentToday' => new AppointmentFacts('a1', new \DateTimeImmutable('2026-10-20T21:00:00Z'), 'scheduled', 'visit'),
            'deal' => new DealFacts('d1', 'engaged', Facts::ago('9 days'), Facts::ago('9 days')),
        ]);

        $rows = (new RuleBasedPrioritizer(30))->prioritize([$customer], Facts::now());

        self::assertCount(1, $rows, 'Four rules fire; the customer still gets one row.');
        self::assertSame('unanswered_inbound', $rows[0]->reason->code);
        self::assertTrue($rows[0]->reason->params['question'], 'The question (130) beats the plain unanswered (110), the lead (105), the appointment (80) and the idle deal (59).');
        self::assertSame(130, $rows[0]->reason->score);
    }

    public function testRowsAreOrderedByScoreThenByWhoWaitedLongest(): void
    {
        $lead = Facts::customer(['customerId' => 'lead', 'lead' => new LeadFacts('l1', Facts::ago('20 minutes'), 'phone', 'u1', false)]);
        $idle = Facts::customer(['customerId' => 'idle', 'deal' => new DealFacts('d1', 'proposal', Facts::ago('3 days'), Facts::ago('3 days'))]);
        $quiet = Facts::customer(['customerId' => 'quiet']);
        $appointmentA = Facts::customer(['customerId' => 'later', 'appointmentToday' => new AppointmentFacts('a2', new \DateTimeImmutable('2026-10-20T22:00:00Z'), 'scheduled', 'visit')]);
        $appointmentB = Facts::customer(['customerId' => 'sooner', 'appointmentToday' => new AppointmentFacts('a1', new \DateTimeImmutable('2026-10-20T20:00:00Z'), 'scheduled', 'visit')]);

        $rows = (new RuleBasedPrioritizer(30))->prioritize([$quiet, $idle, $appointmentA, $lead, $appointmentB], Facts::now());

        self::assertSame(['lead', 'sooner', 'later', 'idle'], array_map(static fn ($row) => $row->customer->customerId, $rows), 'Score first (110, 80, 80, 53); a tie goes to the earlier appointment; nobody without a reason.');
    }

    public function testTheSuggestedChannelIsTheLastInboundElseTextElseEmailElseCall(): void
    {
        $prioritizer = new RuleBasedPrioritizer(30);
        $lead = static fn (array $consent, ?ConversationFacts $conversation = null) => Facts::customer([
            'consent' => $consent,
            'conversation' => $conversation,
            'lead' => new LeadFacts('l1', Facts::ago('20 minutes'), 'phone', 'u1', false),
        ]);
        $suggest = static fn (array $consent, ?ConversationFacts $conversation = null): Channel => $prioritizer->prioritize([$lead($consent, $conversation)], Facts::now())[0]->suggestedChannel;

        self::assertSame(Channel::Email, $suggest(self::GRANTED_TEXT, new ConversationFacts(Facts::ago('2 days'), Facts::ago('1 day'), Channel::Email)), 'The customer last wrote by email.');
        self::assertSame(Channel::Text, $suggest(self::GRANTED_TEXT), 'Text when it is consented.');
        self::assertSame(Channel::Email, $suggest(['text' => 'opted_out', 'email' => 'granted', 'call' => 'granted']), 'Email when text is not.');
        self::assertSame(Channel::Call, $suggest(['text' => 'missing', 'email' => 'revoked', 'call' => 'missing']), 'A call when nothing written is consented.');
    }
}
