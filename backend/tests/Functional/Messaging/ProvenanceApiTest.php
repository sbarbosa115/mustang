<?php

namespace App\Tests\Functional\Messaging;

final class ProvenanceApiTest extends MessagingTestCase
{
    public function testASentTextSaysWhoSentItUnderWhichConsentAndTransport(): void
    {
        $this->signInAs('sales2');
        $customer = $this->customerId(11);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $conversation = $this->conversationOf($customer);
        $id = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Hi Lauren'])['id'];

        $provenance = $this->request('GET', "/api/v1/messages/$id/provenance");

        $this->assertStatus(200);
        self::assertSame($id, $provenance['message_id']);
        self::assertSame(['out', 'text', 'sent'], [$provenance['direction'], $provenance['channel'], $provenance['status']]);
        self::assertSame(['id' => $this->userId('sales2'), 'name' => 'Jordan Reyes'], $provenance['sent_by']);
        self::assertSame('+18135551011', $provenance['to_value']);
        self::assertNotEmpty($provenance['from_value']);
        self::assertSame('text', $provenance['consent_state_snapshot']['channel']);
        self::assertArrayHasKey('checked_at', $provenance['consent_state_snapshot']);
        self::assertNotNull($provenance['sent_at']);
    }

    public function testAnInboundMessageHasNoSender(): void
    {
        $this->signInAs('sales');
        $id = $this->inbound('+18135551012', 'Hi')['message_id'];

        $provenance = $this->request('GET', "/api/v1/messages/$id/provenance");

        self::assertSame(['in', 'received', '+18135551012'], [$provenance['direction'], $provenance['status'], $provenance['from_value']]);
        self::assertNull($provenance['sent_by']);
        self::assertNull($provenance['consent_state_snapshot'], 'Receiving needs no consent.');
        self::assertNotNull($provenance['received_at']);
    }

    public function testAnotherDealershipsMessageIsNotFound(): void
    {
        $this->signInAs('sales');
        $id = $this->inbound('+18135551012', 'Hi')['message_id'];

        $this->assertNotFoundForOtherDealership('GET', "/api/v1/messages/$id/provenance");
    }

    public function testAnUnknownMessageIsNotFound(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/messages/not-a-uuid/provenance');

        $this->assertStatus(404);
    }
}
