<?php

namespace App\Customer\Domain\Model;

use App\Shared\Domain\Email;
use App\Shared\Domain\PhoneNumber;

/**
 * How a contact value reaches the customer.
 */
enum ContactKind: string
{
    case Phone = 'phone';
    case Email = 'email';

    /**
     * The value as it is stored and compared: E.164 for a phone, lowercase for an email.
     *
     * @throws \App\Shared\Domain\Error\InvalidValue (invalid_phone or invalid_email)
     */
    public function normalize(string $raw): string
    {
        return match ($this) {
            self::Phone => PhoneNumber::fromString($raw)->e164,
            self::Email => Email::fromString($raw)->value,
        };
    }
}
