<?php

namespace App\Tests\Unit\Messaging;

use App\Messaging\Domain\Model\Conversation;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    private const string DEALERSHIP = '01a150c9-0000-7000-8000-0000000000d1';
    private const string CUSTOMER = '01a150c9-0000-7000-8000-0000000000c1';

    public function testANewConversationIsNeitherUnrepliedNorFumbled(): void
    {
        $conversation = new Conversation(self::DEALERSHIP, self::CUSTOMER);

        self::assertFalse($conversation->isUnreplied(), 'Nobody wrote yet, so nobody is waiting.');
        self::assertFalse($conversation->isFumbled(self::at('12:00'), 30));
        self::assertSame(0, $conversation->unreadCount());
    }

    public function testAnInboundMessageMakesItUnrepliedAndUnread(): void
    {
        $conversation = new Conversation(self::DEALERSHIP, self::CUSTOMER);
        $conversation->recordInbound(self::at('12:00'));
        $conversation->recordInbound(self::at('12:01'));

        self::assertTrue($conversation->isUnreplied(), 'The last message is the customer\'s.');
        self::assertSame(2, $conversation->unreadCount(), 'Each inbound message is one more unread.');
        self::assertEquals(self::at('12:01'), $conversation->lastMessageAt());
    }

    public function testItIsFumbledOnlyAfterTheThreshold(): void
    {
        $conversation = new Conversation(self::DEALERSHIP, self::CUSTOMER);
        $conversation->recordInbound(self::at('12:00'));

        self::assertFalse($conversation->isFumbled(self::at('12:30'), 30), 'Exactly 30 minutes is not yet "more than 30".');
        self::assertTrue($conversation->isFumbled(self::at('12:31'), 30), 'Unreplied for more than 30 minutes is fumbled.');
    }

    public function testAReplyClearsUnrepliedAndFumbled(): void
    {
        $conversation = new Conversation(self::DEALERSHIP, self::CUSTOMER);
        $conversation->recordInbound(self::at('12:00'));
        $conversation->recordOutbound(self::at('13:00'));

        self::assertFalse($conversation->isUnreplied(), 'The rep answered last.');
        self::assertFalse($conversation->isFumbled(self::at('15:00'), 30));
        self::assertEquals(self::at('13:00'), $conversation->lastOutboundAt());
    }

    public function testReadingResetsTheUnreadCount(): void
    {
        $conversation = new Conversation(self::DEALERSHIP, self::CUSTOMER);
        $conversation->recordInbound(self::at('12:00'));
        $conversation->markRead();

        self::assertSame(0, $conversation->unreadCount());
        self::assertTrue($conversation->isUnreplied(), 'Reading is not answering.');
    }

    public function testTheFirstRepToWriteTakesAnUnassignedConversation(): void
    {
        $conversation = new Conversation(self::DEALERSHIP, self::CUSTOMER);
        $conversation->assignIfUnassigned('rep-1');
        $conversation->assignIfUnassigned('rep-2');

        self::assertSame('rep-1', $conversation->assignedUserId(), 'An assigned conversation keeps its rep.');
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-18 '.$time, new \DateTimeZone('UTC'));
    }
}
