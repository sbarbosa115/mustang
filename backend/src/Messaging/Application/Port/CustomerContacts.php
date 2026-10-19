<?php

namespace App\Messaging\Application\Port;

/**
 * What Messaging needs to know about customers: who owns a phone or email, where to send, and who should answer.
 * Every lookup names the dealership, so the console and the mail worker can use it without a signed-in user.
 */
interface CustomerContacts
{
    /**
     * The customer of this dealership with this contact (E.164 phone or lowercase email), or null.
     *
     * @param 'phone'|'email' $kind
     */
    public function customerWith(string $dealershipId, string $kind, string $value): ?string;

    /**
     * The dealerships that have a customer with this contact.
     *
     * @param 'phone'|'email' $kind
     *
     * @return list<string>
     */
    public function dealershipsWith(string $kind, string $value): array;

    public function exists(string $dealershipId, string $customerId): bool;

    /**
     * The customer's primary phone and email (the first of each kind when none is primary).
     *
     * @return array{phone: ?string, email: ?string}
     */
    public function contactsOf(string $dealershipId, string $customerId): array;

    /**
     * Who answers this customer by default: Sales 1, else BDC.
     */
    public function defaultAssignee(string $dealershipId, string $customerId): ?string;

    public function nameOf(string $dealershipId, string $customerId): string;
}
