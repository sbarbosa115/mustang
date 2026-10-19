<?php

namespace App\Appointment\Application\Port;

/**
 * Who to remind and what to tell them, read at send time so a rep who was reassigned gets the reminder.
 */
interface ReminderDetails
{
    /**
     * @return array{rep_email: string, rep_name: string, customer_name: string, timezone: string}|null null when the rep or the customer is gone
     */
    public function for(string $dealershipId, string $userId, string $customerId): ?array;
}
