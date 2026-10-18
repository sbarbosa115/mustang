<?php

namespace App\Inventory\Application\Import;

use App\Inventory\Domain\Model\VehicleCondition;
use App\Inventory\Domain\Model\VehicleStatus;
use App\Shared\Domain\Error\InvalidValue;

/**
 * One validated row of vehicles.csv: stock_number, vin, year, make, model, trim, body, drivetrain, fuel,
 * exterior_color, interior_color, mileage, condition, price (dollars), status, photo_url.
 */
final readonly class VehicleRow
{
    public const array REQUIRED = ['stock_number', 'year', 'make', 'model', 'condition'];

    private function __construct(
        public string $stockNumber,
        public int $year,
        public string $make,
        public string $model,
        public VehicleCondition $condition,
        public ?VehicleStatus $status,
        public ?string $vin,
        public ?string $trim,
        public ?string $body,
        public ?string $drivetrain,
        public ?string $fuel,
        public ?string $exteriorColor,
        public ?string $interiorColor,
        public ?int $mileage,
        public ?int $priceCents,
        public ?string $photoUrl,
    ) {
    }

    /**
     * @param array<string, string> $row
     *
     * @throws InvalidValue with detail.field naming the column
     */
    public static function fromArray(array $row): self
    {
        if (isset($row['__invalid_encoding'])) {
            throw self::bad('', 'The row is not UTF-8 text.');
        }
        $year = self::required($row, 'year');
        if (1 !== preg_match('/^\d{4}$/', $year) || (int) $year < 1950 || (int) $year > 2100) {
            throw self::bad('year', 'The year must be four digits between 1950 and 2100.');
        }

        return new self(
            self::required($row, 'stock_number', 40),
            (int) $year,
            self::required($row, 'make', 60),
            self::required($row, 'model', 60),
            self::condition(self::required($row, 'condition')),
            self::status(self::optional($row, 'status')),
            self::optional($row, 'vin', 17),
            self::optional($row, 'trim', 80),
            self::optional($row, 'body', 40),
            self::optional($row, 'drivetrain', 20),
            self::optional($row, 'fuel', 20),
            self::optional($row, 'exterior_color', 40),
            self::optional($row, 'interior_color', 40),
            self::mileage(self::optional($row, 'mileage')),
            self::price(self::optional($row, 'price')),
            self::photo(self::optional($row, 'photo_url', 500)),
        );
    }

    /**
     * @param array<string, string> $row
     */
    private static function required(array $row, string $field, int $max = 255): string
    {
        $value = self::optional($row, $field, $max);

        return $value ?? throw self::bad($field, \sprintf('%s is required.', $field));
    }

    /**
     * @param array<string, string> $row
     */
    private static function optional(array $row, string $field, int $max = 255): ?string
    {
        $value = trim($row[$field] ?? '');
        if ('' === $value) {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw self::bad($field, \sprintf('%s is longer than %d characters.', $field, $max));
        }

        return $value;
    }

    private static function condition(string $value): VehicleCondition
    {
        return match (strtolower($value)) {
            'new' => VehicleCondition::New,
            'used', 'pre-owned', 'preowned' => VehicleCondition::Used,
            'cpo', 'certified', 'certified pre-owned' => VehicleCondition::Cpo,
            default => throw self::bad('condition', 'The condition must be new, used or cpo.'),
        };
    }

    private static function status(?string $value): ?VehicleStatus
    {
        if (null === $value) {
            return null;
        }

        return match (str_replace([' ', '-'], '_', strtolower($value))) {
            'available' => VehicleStatus::Available,
            'hold', 'on_hold' => VehicleStatus::Hold,
            'sold' => VehicleStatus::Sold,
            'in_transit' => VehicleStatus::InTransit,
            default => throw self::bad('status', 'The status must be available, hold, sold or in_transit.'),
        };
    }

    private static function mileage(?string $value): ?int
    {
        if (null === $value) {
            return null;
        }
        $digits = str_replace([',', ' '], '', $value);
        if (1 !== preg_match('/^\d{1,9}$/', $digits)) {
            throw self::bad('mileage', 'The mileage must be a whole number.');
        }

        return (int) $digits;
    }

    /** Whole or decimal dollars ("24,999", "$24999.50"), stored as cents. */
    private static function price(?string $value): ?int
    {
        if (null === $value) {
            return null;
        }
        $clean = str_replace(['$', ',', ' '], '', $value);
        if (1 !== preg_match('/^\d{1,9}(\.\d{1,2})?$/', $clean)) {
            throw self::bad('price', 'The price must be an amount in dollars.');
        }

        return (int) round((float) $clean * 100);
    }

    private static function photo(?string $value): ?string
    {
        if (null !== $value && 1 !== preg_match('#^https?://\S+$#i', $value)) {
            throw self::bad('photo_url', 'The photo must be an http or https address.');
        }

        return $value;
    }

    private static function bad(string $field, string $message): InvalidValue
    {
        return new InvalidValue('invalid_row', $message, ['field' => $field]);
    }
}
