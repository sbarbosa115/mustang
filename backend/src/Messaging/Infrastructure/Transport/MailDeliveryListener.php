<?php

namespace App\Messaging\Infrastructure\Transport;

use App\Messaging\Application\Command\RecordEmailDelivery;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Follows a queued email on the worker: SMTP accepted it (sent), or Messenger gave up retrying it (failed).
 * Only emails sent by MailerEmailTransport carry the message header; other emails are ignored.
 */
final readonly class MailDeliveryListener
{
    public function __construct(private CommandBus $commands)
    {
    }

    #[AsEventListener]
    public function onSent(SentMessageEvent $event): void
    {
        $sent = $event->getMessage();
        $ids = self::ids($sent->getOriginalMessage());
        if (null !== $ids) {
            $this->commands->dispatch(new RecordEmailDelivery($ids[1], $ids[0], true, $sent->getMessageId()));
        }
    }

    #[AsEventListener]
    public function onWorkerFailure(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof SendEmailMessage) {
            return;
        }
        $ids = self::ids($message->getMessage());
        if (null !== $ids) {
            $reason = mb_substr($event->getThrowable()->getMessage(), 0, 255);
            $this->commands->dispatch(new RecordEmailDelivery($ids[1], $ids[0], false, failedReason: '' === $reason ? null : $reason));
        }
    }

    /**
     * @return array{0: string, 1: string}|null message id, dealership id
     */
    private static function ids(RawMessage $email): ?array
    {
        if (!$email instanceof Message) {
            return null;
        }
        $headers = $email->getHeaders();
        $message = $headers->get(MailerEmailTransport::MESSAGE_HEADER)?->getBodyAsString();
        $dealership = $headers->get(MailerEmailTransport::DEALERSHIP_HEADER)?->getBodyAsString();

        return null === $message || null === $dealership ? null : [$message, $dealership];
    }
}
