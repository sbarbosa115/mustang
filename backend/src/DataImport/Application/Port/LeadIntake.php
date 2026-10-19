<?php

namespace App\DataImport\Application\Port;

/**
 * The Lead context: capturing a lead matches or creates its customer, routes it and raises LeadCaptured (on which the
 * Deal context opens the deal).
 */
interface LeadIntake
{
    public function hasOpenLead(string $dealershipId, string $customerId): bool;

    /**
     * Either $customerId, or the names and a phone or an email of a customer to create.
     *
     * @param string      $source     website, phone, walk_in, manual, import or other
     * @param string|null $assigneeId null for round robin
     *
     * @return string the lead's id
     */
    public function capture(
        string $source,
        ?string $customerId,
        ?string $firstName,
        ?string $lastName,
        ?string $phone,
        ?string $email,
        ?string $channelNote,
        ?string $vehicleInterest,
        ?string $assigneeId,
        ?\DateTimeImmutable $receivedAt,
    ): string;
}
