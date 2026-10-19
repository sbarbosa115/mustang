<?php

namespace App\Worklist\Domain\Rule;

use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Reason;

/**
 * The customer wrote last: 100 plus the minutes waiting, hot beyond the fumbled minutes (30).
 */
final readonly class UnansweredInbound implements Rule
{
    public const string CODE = 'unanswered_inbound';
    private const int BASE = 100;

    public function __construct(private int $hotAfterMinutes)
    {
    }

    public function evaluate(CustomerFacts $customer, Now $now): ?Reason
    {
        return $this->reason($customer, $now, false);
    }

    private function reason(CustomerFacts $customer, Now $now, bool $question): ?Reason
    {
        $conversation = $customer->conversation;
        if (null === $conversation || !$conversation->unanswered() || null === $conversation->lastInboundAt) {
            return null;
        }
        $minutes = $now->minutesSince($conversation->lastInboundAt);

        return new Reason(
            self::CODE,
            ['minutes' => $minutes, 'channel' => $conversation->lastInboundChannel?->value, 'question' => $question],
            self::BASE + $minutes + ($question ? UnansweredQuestion::BONUS : 0),
            $minutes > $this->hotAfterMinutes,
            $conversation->lastInboundAt,
        );
    }

    /**
     * @internal for UnansweredQuestion, which is the same reason with the question's bonus
     */
    public function withQuestion(CustomerFacts $customer, Now $now): ?Reason
    {
        return $this->reason($customer, $now, true);
    }
}
