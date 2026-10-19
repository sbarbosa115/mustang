<?php

namespace App\Messaging\Infrastructure\Transport;

use App\Messaging\Application\Command\RecordEmailDelivery;
use App\Shared\Application\Bus\CommandBus;
use Psr\Log\LoggerInterface;
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
    /** What a rep is told: the mail server's own error names its host and port, so it goes to the log only. */
    public const string FAILED_REASON = 'delivery_failed';

    public function __construct(private CommandBus $commands, private LoggerInterface $logger)
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
            $this->logger->warning('An email could not be delivered', ['message_id' => $ids[0], 'exception' => $event->getThrowable()]);
            $this->commands->dispatch(new RecordEmailDelivery($ids[1], $ids[0], false, failedReason: self::FAILED_REASON));
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
