<?php

namespace App\Messaging\Infrastructure\Transport;

use App\Messaging\Application\Port\MessageTransport;
use App\Messaging\Application\Port\OutboundMessage;
use App\Messaging\Application\Port\TransportReceipt;
use App\Messaging\Domain\Model\MessageStatus;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Id;
use Psr\Log\LoggerInterface;

/**
 * Texts never leave the stack (MESSAGING_SMS_TRANSPORT=fake): a text is "sent" at once with a fake carrier id.
 */
final readonly class FakeSmsTransport implements MessageTransport
{
    public const string NAME = 'fake_sms';
    /** The dealership's texting number in the fake carrier (a 555 number, never routable). */
    public const string NUMBER = '+18135550100';

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function senderFor(Channel $channel): string
    {
        return self::NUMBER;
    }

    public function send(OutboundMessage $message): TransportReceipt
    {
        $id = 'fake-'.Id::new();
        $this->logger->info('Fake SMS sent', ['message_id' => $message->messageId, 'to' => $message->to, 'transport_message_id' => $id]);

        return new TransportReceipt(self::NAME, MessageStatus::Sent, $id);
    }
}
