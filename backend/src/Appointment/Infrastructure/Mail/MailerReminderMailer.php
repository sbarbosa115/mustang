<?php

namespace App\Appointment\Infrastructure\Mail;

use App\Appointment\Application\Port\ReminderMailer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Symfony Mailer queues the email on the "mail" Messenger queue, in the transaction of the command that sends it.
 */
final readonly class MailerReminderMailer implements ReminderMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%app.mail_from%')] private string $mailFrom,
    ) {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $this->mailer->send(new Email()->from(Address::create($this->mailFrom))->to($to)->subject($subject)->text($body));
    }
}
