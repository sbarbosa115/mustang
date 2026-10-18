<?php

namespace App\Tests\Functional\Messaging;

final class ConversationQueriesTest extends MessagingTestCase
{
    /** @var array{fumbled: string, waiting: string, answered: string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAs('sales');
        $this->startWithAnEmptyInbox();

        // Customer 2 (Sales 1: sales) wrote 45 minutes ago: fumbled. Customer 3 (Sales 1: sales2) wrote 5 minutes ago:
        // waiting. Customer 1 (Sales 1: sales2) wrote and was answered.
        $fumbled = (string) $this->inbound('+18135551002', 'Do you take trade-ins?')['conversation_id'];
        $this->customerWroteMinutesAgo($fumbled, 45);
        $waiting = (string) $this->inbound('+18135551003', 'Hello')['conversation_id'];
        $this->customerWroteMinutesAgo($waiting, 5);
        $customer = $this->customerId(1);
        $answered = (string) $this->inbound('+18135551001', 'Price?')['conversation_id'];
        $this->customerWroteMinutesAgo($answered, 10);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $this->request('POST', "/api/v1/conversations/$answered/messages", ['channel' => 'text', 'body' => '$31,900 out the door.']);
        $this->assertStatus(201);
        $this->ids = ['fumbled' => $fumbled, 'waiting' => $waiting, 'answered' => $answered];
    }

    public function testTheFoldersSplitWhoIsWaiting(): void
    {
        $all = $this->request('GET', '/api/v1/conversations');
        $this->assertStatus(200);
        self::assertSame(3, $all['total']);
        self::assertSame([$this->ids['answered'], $this->ids['waiting'], $this->ids['fumbled']], array_column($all['items'], 'id'), 'Most recent message first.');

        $unreplied = $this->request('GET', '/api/v1/conversations?folder=unreplied');
        self::assertEqualsCanonicalizing([$this->ids['fumbled'], $this->ids['waiting']], array_column($unreplied['items'], 'id'), 'Unreplied: the customer wrote last.');

        $fumbled = $this->request('GET', '/api/v1/conversations?folder=fumbled');
        self::assertSame([$this->ids['fumbled']], array_column($fumbled['items'], 'id'), 'Fumbled: unreplied for more than 30 minutes.');
        self::assertTrue($fumbled['items'][0]['fumbled']);
        self::assertTrue($fumbled['items'][0]['unreplied']);
    }

    public function testEachRowCarriesTheCustomerTheLastMessageAndConsent(): void
    {
        $items = $this->request('GET', '/api/v1/conversations')['items'];
        $answered = $items[0];

        self::assertSame('Sarah Johnson', $answered['customer']['name']);
        self::assertSame('$31,900 out the door.', $answered['last_message']['body']);
        self::assertSame('out', $answered['last_message']['direction']);
        self::assertSame(['text' => 'granted', 'email' => 'missing', 'call' => 'missing'], $answered['consent']);
        self::assertFalse($answered['unreplied']);
        self::assertSame(1, $answered['unread_count'], 'Replying does not mark the thread read; opening it does.');
    }

    public function testCountsPerFolderAndPerRep(): void
    {
        $counts = $this->request('GET', '/api/v1/conversations/counts');

        $this->assertStatus(200);
        self::assertSame([3, 2, 1], [$counts['all'], $counts['unreplied'], $counts['fumbled']]);
        $byUser = array_column(array_map(static fn (array $row): array => ['id' => $row['user']['id'], 'count' => $row['count']], $counts['by_user']), 'count', 'id');
        self::assertEqualsCanonicalizing([$this->userId('sales') => 1, $this->userId('sales2') => 1], $byUser, 'Unreplied conversations per assigned rep; reps with none are left out.');
    }

    public function testTheRepFilterAndTheSearch(): void
    {
        $mine = $this->request('GET', '/api/v1/conversations?user_id='.$this->userId('sales'));
        self::assertSame([$this->ids['fumbled']], array_column($mine['items'], 'id'), 'Only customer 2 is assigned to sales@.');
        $unreplied = $this->request('GET', '/api/v1/conversations?folder=unreplied&user_id='.$this->userId('sales2'));
        self::assertSame([$this->ids['waiting']], array_column($unreplied['items'], 'id'), 'Folder and rep combine.');

        self::assertSame([$this->ids['waiting']], array_column($this->request('GET', '/api/v1/conversations?q=jessica')['items'], 'id'), 'q matches the customer name.');
        self::assertSame([$this->ids['fumbled']], array_column($this->request('GET', '/api/v1/conversations?q=555-1002')['items'], 'id'), 'q matches phone digits.');
        self::assertSame([], $this->request('GET', '/api/v1/conversations?q=%25')['items'], 'A "%" is matched literally.');
    }

    public function testPagination(): void
    {
        $page = $this->request('GET', '/api/v1/conversations?per_page=2&page=2');

        self::assertSame(3, $page['total']);
        self::assertSame(2, $page['page']);
        self::assertSame(2, $page['per_page']);
        self::assertSame([$this->ids['fumbled']], array_column($page['items'], 'id'));
    }

    public function testAnUnknownFolderIsAValidationError(): void
    {
        $this->request('GET', '/api/v1/conversations?folder=spam');

        $this->assertStatus(422);
    }

    public function testFindOrCreateIsIdempotent(): void
    {
        $customer = $this->customerId(20);

        $first = $this->conversationOf($customer);
        $second = $this->conversationOf($customer);

        self::assertSame($first, $second, 'One conversation per customer per dealership.');
        $this->request('GET', '/api/v1/customers/01a150c9-0000-7000-8000-000000000000/conversation');
        $this->assertStatus(404);
    }

    public function testAnotherDealershipSeesNothing(): void
    {
        $this->signInAs('other');

        self::assertSame(0, $this->request('GET', '/api/v1/conversations')['total']);
        $counts = $this->request('GET', '/api/v1/conversations/counts');
        self::assertSame([0, 0, 0, []], [$counts['all'], $counts['unreplied'], $counts['fumbled'], $counts['by_user']]);

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/conversations/'.$this->ids['waiting']);
        $this->assertNotFoundForOtherDealership('POST', '/api/v1/conversations/'.$this->ids['waiting'].'/read');
        $this->assertNotFoundForOtherDealership('GET', '/api/v1/customers/'.$this->customerId(3).'/conversation');
    }
}
