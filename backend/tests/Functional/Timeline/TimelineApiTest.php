<?php

namespace App\Tests\Functional\Timeline;

use App\Shared\Domain\Id;
use App\Tests\Support\ApiTestCase;

final class TimelineApiTest extends ApiTestCase
{
    public function testTheTimelineIsNewestFirstWithTheActorNamed(): void
    {
        $customer = $this->customerId(26);
        $this->add($customer, 'note', 'note_added', '2026-10-01 09:00:00', $this->userId('sales'));
        $this->add($customer, 'lead', 'lead_captured', '2026-09-28 09:00:00');
        $this->add($customer, 'message_out', 'message_sent_text', '2026-10-03 09:00:00');
        $this->signInAs('sales');

        $body = $this->request('GET', '/api/v1/customers/'.$customer.'/timeline');

        $this->assertStatus(200);
        self::assertSame(3, $body['total']);
        self::assertSame(['message_sent_text', 'note_added', 'lead_captured'], array_column($body['items'], 'code'));
        self::assertSame(['message_out', 'note', 'lead'], array_column($body['items'], 'kind'));
        self::assertSame($this->userId('sales'), $body['items'][1]['actor']['id']);
        self::assertNotSame('', $body['items'][1]['actor']['name']);
        self::assertNull($body['items'][0]['actor']);
    }

    public function testItIsPaginated(): void
    {
        $customer = $this->customerId(27);
        for ($i = 1; $i <= 5; ++$i) {
            $this->add($customer, 'note', 'note_added', \sprintf('2026-10-0%d 09:00:00', $i));
        }
        $this->signInAs('manager');

        $second = $this->request('GET', '/api/v1/customers/'.$customer.'/timeline?page=2&per_page=2');

        $this->assertStatus(200);
        self::assertSame([5, 2, 2], [$second['total'], $second['page'], $second['per_page']]);
        self::assertSame(['2026-10-03', '2026-10-02'], array_map(static fn (array $a) => substr($a['occurred_at'], 0, 10), $second['items']));
    }

    public function testACustomerWithoutHistoryHasAnEmptyPage(): void
    {
        $this->signInAs('sales');

        $body = $this->request('GET', '/api/v1/customers/'.$this->customerId(28).'/timeline');

        $this->assertStatus(200);
        self::assertSame([[], 0], [$body['items'], $body['total']]);
    }

    public function testAnotherDealershipGetsNotFoundAndSeesNoRows(): void
    {
        $customer = $this->customerId(26);
        $this->add($customer, 'note', 'note_added', '2026-10-01 09:00:00');

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/customers/'.$customer.'/timeline');
    }

    public function testAnUnknownCustomerIsNotFound(): void
    {
        $this->signInAs('sales');

        $this->request('GET', '/api/v1/customers/'.Id::new().'/timeline');

        $this->assertStatus(404);
    }

    public function testABadPageSizeIsRefused(): void
    {
        $this->signInAs('sales');

        $this->request('GET', '/api/v1/customers/'.$this->customerId(26).'/timeline?per_page=500');

        $this->assertStatus(422);
    }

    public function testVisitorsAreNotLetIn(): void
    {
        $this->request('GET', '/api/v1/customers/'.$this->customerId(26).'/timeline');

        $this->assertStatus(401);
    }

    private function add(string $customer, string $kind, string $code, string $at, ?string $actor = null): void
    {
        $this->db()->insert('activities', [
            'id' => Id::new(),
            'dealership_id' => $this->dealershipId(),
            'customer_id' => $customer,
            'kind' => $kind,
            'occurred_at' => $at,
            'summary' => 'x',
            'actor_user_id' => $actor,
            'event_id' => $code.':'.bin2hex(random_bytes(8)),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
