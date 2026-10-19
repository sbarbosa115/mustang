<?php

namespace App\Shared\Domain;

use App\Shared\Domain\Error\InvalidValue;

/**
 * An email address, trimmed and lowercased so lookups and de-duplication match.
 */
final readonly class Email implements \Stringable
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $value = mb_strtolower(trim($raw));
        if (false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidValue('invalid_email', \sprintf('"%s" is not a valid email address.', $raw), ['value' => $raw]);
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
