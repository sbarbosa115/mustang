<?php

namespace App\Messaging\Application\Port;

/**
 * The Lead context, for a message from a contact no customer of the dealership has: capturing a lead (source other)
 * matches or creates the customer through the Customer context, routes the lead round robin and raises LeadCaptured
 * (on which the Deal context opens the deal). Messaging never creates customers or leads itself.
 */
interface LeadIntake
{
    /**
     * Runs in the dealership the signed-in (or acting) user belongs to.
     *
     * @return string the lead's id
     */
    public function captureFirstContact(?string $phone, ?string $email, string $firstName, string $lastName, string $channelNote): string;
}
