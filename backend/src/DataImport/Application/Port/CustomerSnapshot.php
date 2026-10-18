<?php

namespace App\DataImport\Application\Port;

/**
 * What an import compares a row with: a customer's names, contacts and team as they stand.
 */
final readonly class CustomerSnapshot
{
    /**
     * @param list<string> $phones E.164
     * @param list<string> $emails lowercase
     */
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public array $phones,
        public array $emails,
        public ?string $sales1UserId,
        public ?string $sales2UserId,
        public ?string $bdcUserId,
    ) {
    }
}
