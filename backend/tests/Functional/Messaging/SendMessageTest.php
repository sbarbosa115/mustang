<?php

namespace App\Tests\Functional\Messaging;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class SendMessageTest extends MessagingTestCase
{
    public function testATextIsSentThroughTheFakeTransportWithItsProvenance(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(1);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $conversation = $this->conversationOf($customer);

        $message = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Hi Sarah, the Tacoma is in.']);

        $this->assertStatus(201);
        self::assertSame('sent', $message['status'], 'The fake SMS transport sends at once.');
        self::assertSame('out', $message['direction']);
        self::assertSame('+18135551001', $message['to_value'], 'A text goes to the customer\'s primary phone.');
        self::assertSame('Hi Sarah, the Tacoma is in.', $message['body']);
        self::assertSame($this->userId('sales'), $message['sent_by']['id']);
        self::assertNotNull($message['sent_at']);

        $provenance = $this->request('GET', '/api/v1/messages/'.$message['id'].'/provenance');
        $this->assertStatus(200);
        self::assertSame('fake_sms', $provenance['transport']);
        self::assertStringStartsWith('fake-', (string) $provenance['transport_message_id']);
        self::assertSame('granted', $provenance['consent_state_snapshot']['state'], 'The consent the send relied on is kept with the message.');
        self::assertSame('+18135551001', $provenance['consent_state_snapshot']['contact_value']);

        $thread = $this->request('GET', "/api/v1/conversations/$conversation");
        self::assertFalse($thread['conversation']['unreplied']);
        self::assertSame($message['id'], $thread['conversation']['last_message']['id'], 'The sent text is the last message of the thread.');
    }

    public function testAnEmailIsQueuedThenSentAndLandsInMailpit(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(4);
        $this->withoutConsent($customer);
        $this->consent($customer, 'email');
        $conversation = $this->conversationOf($customer);
        $subject = 'Your test drive '.bin2hex(random_bytes(4));

        $message = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'email', 'subject' => $subject, 'body' => 'See you Saturday at 10.']);

        $this->assertStatus(201);
        self::assertSame('queued', $message['status'], 'An email waits on the mail queue until the worker sends it.');
        self::assertSame('david.jones@example.com', $message['to_value']);

        /** @var InMemoryTransport $queue */
        $queue = static::getContainer()->get('messenger.transport.mail');
        $envelopes = array_values(array_filter($queue->getSent(), static fn (Envelope $e): bool => $e->getMessage() instanceof SendEmailMessage));
        self::assertCount(1, $envelopes, 'One email is on the mail queue.');

        // What the worker does: handle the queued message, which sends it over SMTP to Mailpit.
        static::getContainer()->get(MessageBusInterface::class)->dispatch($envelopes[0]->with(new ReceivedStamp('mail')));

        $row = $this->db()->fetchAssociative('SELECT status, sent_at, transport, transport_message_id FROM messages WHERE id = ?', [$message['id']]);
        self::assertSame('sent', $row['status'] ?? null, 'Delivery by the worker marks the message sent.');
        self::assertNotNull($row['sent_at']);
        self::assertSame('mailer', $row['transport']);
        self::assertNotEmpty($row['transport_message_id']);

        $search = json_decode((string) file_get_contents('http://mailpit:8025/api/v1/search?query='.rawurlencode('subject:"'.$subject.'"')), true);
        self::assertSame(1, $search['messages_count'] ?? null, 'The email lands in Mailpit.');
        self::assertSame('david.jones@example.com', $search['messages'][0]['To'][0]['Address']);
    }

    public function testAnEmailTheWorkerGivesUpOnIsMarkedFailed(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(4);
        $this->withoutConsent($customer);
        $this->consent($customer, 'email');
        $conversation = $this->conversationOf($customer);
        $message = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'email', 'subject' => 'Bounce', 'body' => 'Hello']);
        /** @var InMemoryTransport $queue */
        $queue = static::getContainer()->get('messenger.transport.mail');
        $envelope = array_values($queue->getSent())[0];

        // What the worker reports when the last of the mail queue's 5 retries failed.
        $failure = new WorkerMessageFailedEvent($envelope->with(new RedeliveryStamp(5)), 'mail', new \RuntimeException('Connection could not be established with host "smtp.internal:2525": Connection refused'));
        static::getContainer()->get(EventDispatcherInterface::class)->dispatch($failure);

        $row = $this->db()->fetchAssociative('SELECT status, failed_reason FROM messages WHERE id = ?', [$message['id']]);
        // Security audit 2026-10-18, finding 6: the mail server's own error (its host and port) reached the API.
        self::assertSame(['status' => 'failed', 'failed_reason' => 'delivery_failed'], $row, 'The rep sees the email never left, not the server\'s error.');
    }

    public function testATextWithoutConsentIsBlockedAndTheRefusalIsKept(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(5);
        $this->withoutConsent($customer);
        $conversation = $this->conversationOf($customer);

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Hello?']);

        $this->assertError(409, 'consent_missing', $error, 'No consent row means no send.');
        self::assertSame('missing', $error['detail']['consent']['state']);
        $provenance = $this->request('GET', '/api/v1/messages/'.$error['detail']['message_id'].'/provenance');
        $this->assertStatus(200, 'The refused attempt is stored for provenance.');
        self::assertSame('blocked', $provenance['status']);
        self::assertSame('consent_missing', $provenance['failed_reason']);
        self::assertSame('missing', $provenance['consent_state_snapshot']['state']);
        self::assertNull($provenance['transport'], 'A blocked message never reaches a transport.');

        $thread = $this->request('GET', "/api/v1/conversations/$conversation");
        self::assertNull($thread['conversation']['last_message_at'], 'A blocked attempt does not count as a message sent.');
    }

    public function testARevokedConsentIsAnOptOut(): void
    {
        $this->signInAs('bdc');
        $customer = $this->customerId(6);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $this->consent($customer, 'text', 'revoked');
        $conversation = $this->conversationOf($customer);

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Still there?']);

        $this->assertError(409, 'opted_out', $error, 'The latest row is revoked.');
        self::assertSame('revoked', $error['detail']['consent']['state']);
    }

    public function testASuppressedContactIsAnOptOutEvenWithConsent(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(7);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $this->suppress('+18135551007', 'text');
        $conversation = $this->conversationOf($customer);

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Deal of the week']);

        $this->assertError(409, 'opted_out', $error, 'A STOP anywhere blocks every dealership.');
        self::assertSame('opted_out', $error['detail']['consent']['state']);
    }

    public function testTheTemplateUsedIsRecorded(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(8);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $conversation = $this->conversationOf($customer);
        $template = $this->template($this->dealershipId());

        $message = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Hi James, thanks for reaching out!', 'template_id' => $template]);

        $this->assertStatus(201);
        self::assertSame($template, $message['template_id'], 'The server stores the body as sent and which template it came from.');
        self::assertSame($template, $this->request('GET', '/api/v1/messages/'.$message['id'].'/provenance')['template_id']);
    }

    public function testAnotherDealershipsTemplateIsNotFound(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(8);
        $this->withoutConsent($customer);
        $this->consent($customer, 'text');
        $conversation = $this->conversationOf($customer);

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Hi', 'template_id' => $this->template($this->dealershipId('other'))]);

        $this->assertError(404, 'template_not_found', $error);
    }

    public function testAnEmailNeedsASubject(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(4);
        $this->withoutConsent($customer);
        $this->consent($customer, 'email');
        $conversation = $this->conversationOf($customer);

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'email', 'body' => 'No subject']);

        $this->assertError(422, 'subject_required', $error);
    }

    public function testACallIsNotAMessage(): void
    {
        $this->signInAs('sales');
        $conversation = $this->conversationOf($this->customerId(1));

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'call', 'body' => 'Ring ring']);

        $this->assertError(422, 'channel_not_sendable', $error);
    }

    public function testABlankBodyIsAValidationError(): void
    {
        $this->signInAs('sales');
        $conversation = $this->conversationOf($this->customerId(1));

        $error = $this->request('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => '']);

        $this->assertError(422, 'validation', $error);
        self::assertSame('body', $error['violations'][0]['field']);
    }

    public function testAnotherDealershipCannotSendIntoTheConversation(): void
    {
        $this->signInAs('sales');
        $conversation = $this->conversationOf($this->customerId(1));

        $this->assertNotFoundForOtherDealership('POST', "/api/v1/conversations/$conversation/messages", ['channel' => 'text', 'body' => 'Hi']);
    }

    private function template(string $dealershipId): string
    {
        $id = '01a150c9-0000-7000-8000-'.bin2hex(random_bytes(6));
        $this->db()->executeStatement(
            "INSERT INTO message_templates (id, dealership_id, name, channel, subject, body, active, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, 'First reply', 'text', NULL, 'Hi {first_name}, thanks for reaching out!', 1, ?, NOW(), NOW())",
            [$id, $dealershipId, $this->userId('manager')],
        );

        return $id;
    }
}
