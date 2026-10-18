<?php

namespace App\Messaging\Infrastructure\Transport;

use App\Messaging\Application\Port\MessageTransport;
use App\Messaging\Application\Port\OutboundMessage;
use App\Messaging\Application\Port\TransportReceipt;
use App\Messaging\Domain\Model\MessageStatus;
use App\Shared\Domain\Channel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Emails go through Symfony Mailer, which puts them on the "mail" Messenger queue (in the same transaction as the
 * message row). The worker sends them; MailDeliveryListener then marks the message sent or failed.
 */
final readonly class MailerEmailTransport implements MessageTransport
{
    public const string NAME = 'mailer';
    public const string MESSAGE_HEADER = 'X-Mustang-Message-Id';
    public const string DEALERSHIP_HEADER = 'X-Mustang-Dealership-Id';

    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%app.mail_from%')] private string $mailFrom,
    ) {
    }

    public function senderFor(Channel $channel): string
    {
        return Address::create($this->mailFrom)->getAddress();
    }

    public function send(OutboundMessage $message): TransportReceipt
    {
        $email = new Email()
            ->from(Address::create($this->mailFrom))
            ->to($message->to)
            ->subject((string) $message->subject)
            ->text($message->body);
        $email->getHeaders()
            ->addTextHeader(self::MESSAGE_HEADER, $message->messageId)
            ->addTextHeader(self::DEALERSHIP_HEADER, $message->dealershipId);
        $this->mailer->send($email);

        return new TransportReceipt(self::NAME, MessageStatus::Queued);
    }
}
