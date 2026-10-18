<?php

namespace App\DataImport\Application\Port;

/**
 * The current dealership's customers, through the Customer context's own commands and queries.
 */
interface CustomerRecords
{
    /**
     * The customer with this phone (E.164) or, when $phone is null, this email (lowercase).
     */
    public function findByContact(?string $phone, ?string $email): ?CustomerSnapshot;

    /**
     * @return string the new customer's id
     */
    public function create(string $firstName, string $lastName, ?string $phone, ?string $email, ?string $sales1UserId, string $source): string;

    public function rename(string $customerId, string $firstName, string $lastName): void;

    /**
     * @param 'phone'|'email' $kind
     */
    public function addContact(string $customerId, string $kind, string $value): void;

    public function assignTeam(string $customerId, ?string $sales1UserId, ?string $sales2UserId, ?string $bdcUserId): void;

    public function hasNote(string $customerId, string $body): bool;

    public function addNote(string $customerId, string $authorUserId, string $body): void;
}
