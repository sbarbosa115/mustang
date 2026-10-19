<?php

namespace App\Appointment\Application\Port;

/**
 * Sends the rep's reminder (plain text, from MAIL_FROM).
 */
interface ReminderMailer
{
    public function send(string $to, string $subject, string $body): void;
}
