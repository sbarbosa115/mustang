<?php

namespace App\Messaging\Infrastructure\Transport;

use App\Messaging\Application\Port\MessageTransport;
use App\Messaging\Application\Port\OutboundMessage;
use App\Messaging\Application\Port\TransportReceipt;
use App\Shared\Domain\Channel;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The MessageTransport the application uses: email through the mailer, text through the SMS transport named by
 * MESSAGING_SMS_TRANSPORT (only "fake" exists until a carrier is integrated).
 */
#[AsAlias(MessageTransport::class)]
final readonly class ChannelTransport implements MessageTransport
{
    public function __construct(
        private MailerEmailTransport $email,
        private FakeSmsTransport $fakeSms,
        #[Autowire('%env(MESSAGING_SMS_TRANSPORT)%')] private string $smsTransport,
    ) {
    }

    public function senderFor(Channel $channel): string
    {
        return $this->for($channel)->senderFor($channel);
    }

    public function send(OutboundMessage $message): TransportReceipt
    {
        return $this->for($message->channel)->send($message);
    }

    private function for(Channel $channel): MessageTransport
    {
        return match ($channel) {
            Channel::Email => $this->email,
            Channel::Text => match ($this->smsTransport) {
                'fake' => $this->fakeSms,
                default => throw new \LogicException(\sprintf('Unknown MESSAGING_SMS_TRANSPORT "%s"; the MVP has only "fake".', $this->smsTransport)),
            },
            Channel::Call => throw new \LogicException('A call has no message transport.'),
        };
    }
}
