<?php

namespace App\DataImport\Application\Import;

use App\Shared\Domain\Email;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\PhoneNumber;

/**
 * Reads the cells of one row. Every refusal is an InvalidValue naming the column in detail.field.
 */
final readonly class RowValues
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(private array $values)
    {
        if (isset($values['__invalid_encoding'])) {
            throw new InvalidValue('row_not_utf8', 'The row is not UTF-8 text.');
        }
    }

    public function text(string $column, int $max): ?string
    {
        $value = trim($this->values[$column] ?? '');
        if ('' === $value) {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidValue('too_long', \sprintf('%s is longer than %d characters.', $column, $max), ['field' => $column]);
        }

        return $value;
    }

    /**
     * Cut to $max characters instead of refusing the row: for free text the old CRM may have let grow.
     */
    public function clipped(string $column, int $max): ?string
    {
        $value = trim($this->values[$column] ?? '');

        return '' === $value ? null : mb_substr($value, 0, $max);
    }

    public function phone(string $column = 'phone'): ?string
    {
        $raw = $this->text($column, 40);
        if (null === $raw) {
            return null;
        }
        try {
            return PhoneNumber::fromString($raw)->e164;
        } catch (InvalidValue $e) {
            throw new InvalidValue('invalid_phone', $e->getMessage(), ['field' => $column]);
        }
    }

    public function email(string $column): ?string
    {
        $raw = $this->text($column, 180);
        if (null === $raw) {
            return null;
        }
        try {
            return Email::fromString($raw)->value;
        } catch (InvalidValue $e) {
            throw new InvalidValue('invalid_email', $e->getMessage(), ['field' => $column]);
        }
    }

    /**
     * yes, y, true, 1 or x; no, n, false, 0 or empty.
     */
    public function yesNo(string $column): bool
    {
        $value = strtolower(trim($this->values[$column] ?? ''));

        return match ($value) {
            'yes', 'y', 'true', '1', 'x' => true,
            '', 'no', 'n', 'false', '0' => false,
            default => throw new InvalidValue('invalid_yes_no', \sprintf('%s must be yes or no.', $column), ['field' => $column]),
        };
    }
}
