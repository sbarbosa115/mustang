<?php

namespace App\Shared\Domain;

use App\Shared\Domain\Error\InvalidValue;

/**
 * A phone number in E.164. Ten-digit numbers are read as US/Canada (+1), which is where Mustang's dealers are.
 */
final readonly class PhoneNumber implements \Stringable
{
    private function __construct(public string $e164)
    {
    }

    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (str_starts_with($trimmed, '+')) {
            $e164 = '+'.$digits;
        } elseif (10 === \strlen($digits)) {
            $e164 = '+1'.$digits;
        } elseif (11 === \strlen($digits) && str_starts_with($digits, '1')) {
            $e164 = '+'.$digits;
        } else {
            throw new InvalidValue('invalid_phone', \sprintf('"%s" is not a valid phone number.', $raw), ['value' => $raw]);
        }

        if (1 !== preg_match('/^\+[1-9]\d{7,14}$/', $e164)) {
            throw new InvalidValue('invalid_phone', \sprintf('"%s" is not a valid phone number.', $raw), ['value' => $raw]);
        }

        return new self($e164);
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
