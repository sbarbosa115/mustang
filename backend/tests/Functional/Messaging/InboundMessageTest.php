<?php

namespace App\Tests\Functional\Messaging;

use App\Messaging\Domain\Event\InboundMessageReceived;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\TraceableMessageBus;

final class InboundMessageTest extends MessagingTestCase
{
    public function testAReplyFromAKnownPhoneOpensTheCustomersConversation(): void
    {
        $this->signInAs('bdc');
        $this->startWithAnEmptyInbox();

        $result = $this->inbound('(813) 555-1003', 'Is the Camry still available?');

        $this->assertStatus(201);
        self::assertSame($this->customerId(3), $result['customer_id'], 'The phone is matched in any format, as E.164.');
        $thread = $this->request('GET', '/api/v1/conversations/'.$result['conversation_id']);
        $this->assertStatus(200);
        self::assertSame(1, $thread['conversation']['unread_count']);
        self::assertTrue($thread['conversation']['unreplied'], 'The customer wrote last.');
        self::assertFalse($thread['conversation']['fumbled'], 'Not yet 30 minutes.');
        self::assertSame('Jessica Brown', $thread['conversation']['customer']['name']);
        self::assertSame($this->userId('sales2'), $thread['conversation']['assignee']['id'], 'A new conversation goes to the customer\'s Sales 1.');
        self::assertSame(['phone' => '+18135551003', 'email' => 'jessica.brown@example.com'], $thread['contacts']);
        $message = $thread['messages'][0];
        self::assertSame(['in', 'text', 'received', '+18135551003', 'Is the Camry still available?'], [$message['direction'], $message['channel'], $message['status'], $message['from_value'], $message['body']]);
        self::assertNotNull($message['received_at']);
    }

    public function testASecondReplyLandsInTheSameConversation(): void
    {
        $this->signInAs('bdc');
        $this->startWithAnEmptyInbox();

        $first = $this->inbound('+18135551003', 'Hello');
        $second = $this->inbound('+18135551003', 'Anyone there?');

        self::assertSame($first['conversation_id'], $second['conversation_id'], 'One conversation per customer.');
        $thread = $this->request('GET', '/api/v1/conversations/'.$first['conversation_id']);
        self::assertSame(2, $thread['conversation']['unread_count']);
        self::assertSame(['Hello', 'Anyone there?'], array_column($thread['messages'], 'body'), 'Oldest first in the thread.');
    }

    public function testAnEmailReplyIsMatchedByAddressWhateverItsCase(): void
    {
        $this->signInAs('sales');
        $this->startWithAnEmptyInbox();

        $result = $this->inbound('Sarah.Johnson@Example.com', 'Thanks!', 'email', 'Re: your visit');

        $this->assertStatus(201);
        self::assertSame($this->customerId(1), $result['customer_id']);
        $thread = $this->request('GET', '/api/v1/conversations/'.$result['conversation_id']);
        self::assertSame('Re: your visit', $thread['messages'][0]['subject']);
        self::assertSame('email', $thread['messages'][0]['channel']);
    }

    public function testTheReplyRaisesInboundMessageReceived(): void
    {
        $this->signInAs('sales');
        $this->client->disableReboot();

        $result = $this->inbound('+18135551009', 'STOP');

        /** @var TraceableMessageBus $bus the test kernel runs in debug, so every bus is traced */
        $bus = static::getContainer()->get('event.bus');
        $events = array_values(array_filter(
            array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages()),
            static fn (object $m): bool => $m instanceof InboundMessageReceived,
        ));
        self::assertCount(1, $events, 'Compliance (STOP), Deal and Timeline react to this event.');
        $event = $events[0];
        self::assertSame([$result['message_id'], $result['conversation_id'], $this->customerId(9), 'text', '+18135551009', 'STOP', $this->dealershipId()], [$event->messageId, $event->conversationId, $event->customerId, $event->channel, $event->contactValue, $event->body, $event->dealershipId]);
    }

    public function testAnUnknownContactIsRefused(): void
    {
        $this->signInAs('sales');

        $error = $this->inbound('+19995550000', 'Who is this?');

        $this->assertError(422, 'unknown_contact', $error, 'Creating the customer needs the Customer context (see the item report).');
    }

    public function testAnotherDealershipsCustomerIsUnknownHere(): void
    {
        $this->signInAs('other');

        $error = $this->inbound('+18135551001', 'Hi');

        $this->assertError(422, 'unknown_contact', $error, 'A contact of another rooftop is never matched.');
    }

    public function testAMalformedPhoneIsRefused(): void
    {
        $this->signInAs('sales');

        $this->assertError(422, 'invalid_phone', $this->inbound('12', 'Hi'));
    }

    public function testMarkingReadClearsTheUnreadCount(): void
    {
        $this->signInAs('sales');
        $this->startWithAnEmptyInbox();
        $result = $this->inbound('+18135551003', 'Hello');
        $this->inbound('+18135551003', 'Hello again');

        $conversation = $this->request('POST', '/api/v1/conversations/'.$result['conversation_id'].'/read');

        $this->assertStatus(200);
        self::assertSame(0, $conversation['unread_count']);
        self::assertTrue($conversation['unreplied'], 'Reading is not answering.');
    }

    public function testTheConsoleCommandSimulatesAReply(): void
    {
        $this->startWithAnEmptyInbox();
        $tester = new CommandTester(new Application(static::$kernel ?? static::bootKernel())->find('mustang:messaging:simulate-inbound'));

        $code = $tester->execute(['from' => '813-555-1010', 'body' => 'Can I come at 5?']);

        self::assertSame(0, $code, $tester->getDisplay());
        self::assertStringContainsString('Robert Hernandez', $tester->getDisplay(), 'It names the customer the reply was matched to.');
        $row = $this->db()->fetchAssociative(
            "SELECT m.body, c.unread_count FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.direction = 'in' AND c.customer_id = ?",
            [$this->customerId(10)],
        );
        self::assertSame(['body' => 'Can I come at 5?', 'unread_count' => 1], $row);
    }

    public function testTheConsoleCommandRefusesAnUnknownContact(): void
    {
        $tester = new CommandTester(new Application(static::$kernel ?? static::bootKernel())->find('mustang:messaging:simulate-inbound'));

        self::assertSame(1, $tester->execute(['from' => 'nobody@example.com', 'body' => 'Hi', '--channel' => 'email']));
        self::assertStringContainsString('No customer', $tester->getDisplay());
    }
}
