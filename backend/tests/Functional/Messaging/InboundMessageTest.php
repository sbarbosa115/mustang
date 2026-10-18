<?php

namespace App\Tests\Functional\Messaging;

use App\Lead\Domain\Event\LeadCaptured;
use App\Messaging\Domain\Event\InboundMessageReceived;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

    public function testAnUnknownContactBecomesANewCustomerWithALead(): void
    {
        $this->signInAs('sales');
        $this->client->disableReboot();

        $result = $this->inbound('(999) 555-0000', 'Who is this?');

        $this->assertStatus(201, 'An unknown contact is a new lead, not a refusal (PRD, "Messaging without providers").');
        $this->assertNewCustomerWithLead($result, $this->dealershipId(), 'phone', '+19995550000', 'First contact by text', 'Who is this?');
    }

    public function testAnUnknownEmailBecomesANewCustomerWithALead(): void
    {
        $this->signInAs('bdc');
        $this->client->disableReboot();

        $result = $this->inbound('Nobody@Example.com', 'Do you have trucks?', 'email', 'Trucks');

        $this->assertStatus(201);
        $this->assertNewCustomerWithLead($result, $this->dealershipId(), 'email', 'nobody@example.com', 'First contact by email', 'Do you have trucks?');
    }

    public function testAnotherDealershipsCustomerIsNeverMatched(): void
    {
        $this->signInAs('other');
        $this->client->disableReboot();

        $result = $this->inbound('+18135551001', 'Hi');

        $this->assertStatus(201);
        self::assertNotSame($this->customerId(1), $result['customer_id'], 'A contact of another rooftop is never matched.');
        $this->assertNewCustomerWithLead($result, $this->dealershipId('other'), 'phone', '+18135551001', 'First contact by text', 'Hi');
        self::assertSame(0, (int) $this->db()->fetchOne(
            "SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE c.customer_id = ? AND m.direction = 'in' AND m.body = 'Hi'",
            [$this->customerId(1)],
        ), 'Mustang Motors\' customer with that phone gets nothing.');
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

    public function testTheConsoleCommandCreatesACustomerAndALeadForAnUnknownContact(): void
    {
        $kernel = static::$kernel ?? static::bootKernel();
        $tester = new CommandTester(new Application($kernel)->find('mustang:messaging:simulate-inbound'));
        /** @var TokenStorageInterface $tokens */
        $tokens = static::getContainer()->get('security.token_storage');
        self::assertNull($tokens->getToken(), 'Nobody is signed in on the console.');

        $code = $tester->execute(['from' => 'nobody@example.com', 'body' => 'Hi', '--channel' => 'email', '--dealership' => $this->dealershipId()]);

        self::assertSame(0, $code, $tester->getDisplay());
        self::assertNull($tokens->getToken(), 'The acting admin is signed out again afterwards.');
        $customer = $this->db()->fetchAssociative(
            "SELECT c.id, c.source FROM customers c JOIN customer_contacts cc ON cc.customer_id = c.id WHERE cc.kind = 'email' AND cc.value = 'nobody@example.com' AND c.dealership_id = ?",
            [$this->dealershipId()],
        );
        self::assertIsArray($customer, 'The console created the customer in the dealership it was given.');
        $result = $this->db()->fetchAssociative(
            "SELECT c.id AS conversation_id, m.id AS message_id FROM conversations c JOIN messages m ON m.conversation_id = c.id WHERE c.customer_id = ? AND m.direction = 'in'",
            [$customer['id']],
        );
        self::assertIsArray($result);
        $this->assertNewCustomerWithLead(['customer_id' => $customer['id']] + $result, $this->dealershipId(), 'email', 'nobody@example.com', 'First contact by email', 'Hi');
    }

    public function testTheConsoleCommandNeedsTheDealershipForAnUnknownContact(): void
    {
        $tester = new CommandTester(new Application(static::$kernel ?? static::bootKernel())->find('mustang:messaging:simulate-inbound'));

        self::assertSame(1, $tester->execute(['from' => 'nobody@example.com', 'body' => 'Hi', '--channel' => 'email']));
        self::assertStringContainsString('--dealership', $tester->getDisplay(), 'Nothing says which rooftop the new customer belongs to.');
    }

    /**
     * The inbound message created a customer (source other) and a lead in that dealership, raised one LeadCaptured
     * (Deal opens the deal on it) and landed in the new customer's conversation.
     *
     * @param array<string, mixed> $result customer_id, conversation_id and message_id
     */
    private function assertNewCustomerWithLead(array $result, string $dealershipId, string $kind, string $value, string $channelNote, string $body): void
    {
        $customerId = (string) $result['customer_id'];
        $customer = $this->db()->fetchAssociative('SELECT dealership_id, source FROM customers WHERE id = ?', [$customerId]);
        self::assertSame(['dealership_id' => $dealershipId, 'source' => 'other'], $customer, 'A new customer of this dealership, source other.');
        self::assertSame($value, $this->db()->fetchOne(
            'SELECT value FROM customer_contacts WHERE customer_id = ? AND kind = ?',
            [$customerId, $kind],
        ), 'The contact the message came from is the customer\'s.');

        $leads = $this->db()->fetchAllAssociative('SELECT id, dealership_id, source, channel_note FROM leads WHERE customer_id = ?', [$customerId]);
        self::assertCount(1, $leads, 'One lead for the new customer.');
        self::assertSame([$dealershipId, 'other', $channelNote], [$leads[0]['dealership_id'], $leads[0]['source'], $leads[0]['channel_note']]);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM deals WHERE customer_id = ? AND dealership_id = ?', [$customerId, $dealershipId]), 'Deal opens a deal on LeadCaptured.');

        /** @var TraceableMessageBus $bus */
        $bus = static::getContainer()->get('event.bus');
        $events = array_map(static fn (array $m): object => $m['message'], $bus->getDispatchedMessages());
        $captured = array_values(array_filter($events, static fn (object $e): bool => $e instanceof LeadCaptured));
        self::assertCount(1, $captured, 'One LeadCaptured, through the Lead context\'s own CaptureLead.');
        self::assertSame([$leads[0]['id'], $customerId, $dealershipId, 'other'], [$captured[0]->leadId, $captured[0]->customerId, $captured[0]->dealershipId, $captured[0]->source]);
        $received = array_values(array_filter($events, static fn (object $e): bool => $e instanceof InboundMessageReceived));
        self::assertCount(1, $received, 'The message is received as usual.');
        self::assertSame($customerId, $received[0]->customerId);

        $message = $this->db()->fetchAssociative(
            'SELECT m.body, m.direction, c.customer_id, c.dealership_id FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.id = ? AND c.id = ?',
            [$result['message_id'], $result['conversation_id']],
        );
        self::assertSame(['body' => $body, 'direction' => 'in', 'customer_id' => $customerId, 'dealership_id' => $dealershipId], $message, 'The message is in the new customer\'s conversation.');
    }
}
