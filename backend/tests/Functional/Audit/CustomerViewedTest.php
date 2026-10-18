<?php

namespace App\Tests\Functional\Audit;

/**
 * NFR-04: opening a customer record is an access to personal data, recorded as a read.
 */
final class CustomerViewedTest extends AuditTestCase
{
    public function testOpeningACustomerRecordsARead(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(10);

        $this->requestWithId('GET', '/api/v1/customers/'.$id, null, 'req-aud-read-1');
        $this->assertStatus(200);

        $reads = $this->entries('Customer', $id, 'read');
        self::assertCount(1, $reads, 'One opening is one read.');
        self::assertSame($this->userId('sales'), $reads[0]['actor_user_id'], 'Who looked.');
        self::assertSame($id, $reads[0]['customer_id']);
        self::assertSame($this->dealershipId(), $reads[0]['dealership_id']);
        self::assertSame('req-aud-read-1', $reads[0]['request_id']);
        self::assertNotNull($reads[0]['ip'], 'From where.');
        self::assertFalse($reads[0]['has_changes'], 'A read changes nothing.');
    }

    public function testAReadWithoutARequestIdGetsTheOneTheResponseNames(): void
    {
        $this->signInAs('bdc');
        $id = $this->customerId(11);

        $this->request('GET', '/api/v1/customers/'.$id);
        $this->assertStatus(200);

        $reads = $this->entries('Customer', $id, 'read');
        self::assertCount(1, $reads);
        self::assertSame($this->client->getResponse()->headers->get('X-Request-Id'), $reads[0]['request_id']);
    }

    public function testAnotherDealershipsCustomerIsNotFoundAndNothingIsRecorded(): void
    {
        $id = $this->customerId(12);
        $this->assertNotFoundForOtherDealership('GET', '/api/v1/customers/'.$id);

        self::assertSame([], $this->entries('Customer', $id), 'A refused look is not an access.');
    }

    public function testListingCustomersIsNotARecordRead(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/customers');
        $this->assertStatus(200);

        self::assertSame(0, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_entries WHERE action = 'read'"), 'Only opening the record itself is recorded.');
    }
}
