<?php

namespace App\Appointment\Application\Port;

/**
 * What an appointment needs to know about other contexts' records, all within one dealership: its timezone, its
 * reps, its customers and their open deal.
 */
interface AppointmentPeople
{
    /**
     * An IANA name, e.g. America/New_York.
     */
    public function timezone(string $dealershipId): string;

    public function isActiveUser(string $dealershipId, string $userId): bool;

    public function hasCustomer(string $dealershipId, string $customerId): bool;

    public function dealBelongsToCustomer(string $dealershipId, string $dealId, string $customerId): bool;

    /**
     * The customer's open deal (not delivered, not lost), if any.
     */
    public function openDealOf(string $dealershipId, string $customerId): ?string;
}
