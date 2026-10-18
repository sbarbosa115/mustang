<?php

namespace App\Compliance\Application\Port;

/**
 * The contacts of a customer of a dealership, read from the Customer context without depending on it.
 */
interface CustomerContacts
{
    /**
     * The customer's phones (E.164) and emails (lowercase), primary first; null when the dealership has no such customer.
     *
     * @return array{phone: list<string>, email: list<string>}|null
     */
    public function contactsOf(string $dealershipId, string $customerId): ?array;
}
