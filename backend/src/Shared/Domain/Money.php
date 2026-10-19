<?php

namespace App\Shared\Domain;

use App\Shared\Domain\Error\InvalidValue;

/**
 * An amount in US dollars, held as integer cents so sums never drift.
 */
final readonly class Money
{
    private function __construct(public int $cents)
    {
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromDecimal(string $amount): self
    {
        $normalized = str_replace([',', '$', ' '], '', $amount);
        if (1 !== preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidValue('invalid_money', \sprintf('"%s" is not an amount.', $amount), ['value' => $amount]);
        }
        [$whole, $fraction] = array_pad(explode('.', ltrim($normalized, '-')), 2, '0');
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return new self(str_starts_with($normalized, '-') ? -$cents : $cents);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }
}
