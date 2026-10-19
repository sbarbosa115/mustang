<?php

namespace App\Deal\Domain\Model;

/**
 * The car the customer trades in, as far as it is known. Stored on the deal as trade_in_json.
 */
final readonly class TradeIn
{
    public ?string $make;
    public ?string $model;

    public function __construct(
        public ?int $year = null,
        ?string $make = null,
        ?string $model = null,
        public ?int $mileage = null,
        public ?int $payoffCents = null,
    ) {
        $this->make = self::text($make);
        $this->model = self::text($model);
    }

    public function isEmpty(): bool
    {
        return null === $this->year && null === $this->make && null === $this->model && null === $this->mileage && null === $this->payoffCents;
    }

    /**
     * @return array{year: ?int, make: ?string, model: ?string, mileage: ?int, payoff_cents: ?int}
     */
    public function toArray(): array
    {
        return ['year' => $this->year, 'make' => $this->make, 'model' => $this->model, 'mileage' => $this->mileage, 'payoff_cents' => $this->payoffCents];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $int = static fn (string $key): ?int => isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;
        $str = static fn (string $key): ?string => isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;

        return new self($int('year'), $str('make'), $str('model'), $int('mileage'), $int('payoff_cents'));
    }

    private static function text(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
