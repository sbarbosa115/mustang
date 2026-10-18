<?php

namespace App\DataImport\Application\Import;

use App\Shared\Domain\Error\InvalidValue;

/**
 * One row of leads.csv.
 */
final readonly class LeadRow
{
    public const array COLUMNS = ['first_name', 'last_name', 'phone', 'email', 'source', 'vehicle_interest', 'received_at', 'sales_rep_email', 'notes'];
    public const array REQUIRED = ['first_name', 'last_name', 'phone', 'email'];

    /** The Lead context's sources, and the spellings old CRMs use for them. */
    private const array SOURCES = [
        'website' => 'website', 'web' => 'website', 'internet' => 'website', 'online' => 'website',
        'phone' => 'phone', 'call' => 'phone', 'phone_call' => 'phone',
        'walk_in' => 'walk_in', 'walkin' => 'walk_in', 'showroom' => 'walk_in',
        'manual' => 'manual', 'import' => 'import', 'other' => 'other',
    ];

    /** Day-first dates are not accepted: 03/04/2026 is March 4th, as US dealers write it. */
    private const array FORMATS = [
        '!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s.vP', '!Y-m-d\TH:i:s', '!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d',
        '!m/d/Y g:i A', '!m/d/Y g:i a', '!m/d/Y H:i', '!m/d/Y',
    ];

    private function __construct(
        public ContactFields $contact,
        public string $source,
        public ?string $channelNote,
        public ?string $vehicleInterest,
        public ?\DateTimeImmutable $receivedAt,
        public ?string $salesRepEmail,
        public ?string $notes,
    ) {
    }

    /**
     * @param array<string, string> $values
     *
     * @throws InvalidValue with the column in detail.field
     */
    public static function fromArray(array $values, \DateTimeZone $timezone, \DateTimeImmutable $now): self
    {
        $cells = new RowValues($values);
        $contact = ContactFields::from($cells);
        $rawSource = $cells->clipped('source', 255);
        $key = null === $rawSource ? 'import' : (string) preg_replace('/[\s-]+/', '_', strtolower($rawSource));
        $source = self::SOURCES[$key] ?? 'other';

        return new self(
            $contact,
            $source,
            'other' === $source && 'other' !== $key ? $rawSource : null,
            $cells->text('vehicle_interest', 255),
            self::receivedAt($cells->text('received_at', 40), $timezone, $now),
            $cells->email('sales_rep_email'),
            $cells->text('notes', 5000),
        );
    }

    private static function receivedAt(?string $raw, \DateTimeZone $timezone, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        if (null === $raw) {
            return null;
        }
        foreach (self::FORMATS as $format) {
            $at = \DateTimeImmutable::createFromFormat($format, $raw, $timezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if (false !== $at && (false === $errors || 0 === $errors['warning_count'] + $errors['error_count'])) {
                $at = $at->setTimezone(new \DateTimeZone('UTC'));
                if ($at > $now->modify('+5 minutes')) {
                    throw new InvalidValue('received_in_future', 'received_at is in the future.', ['field' => 'received_at']);
                }

                return $at;
            }
        }
        throw new InvalidValue('invalid_received_at', \sprintf('"%s" is not a date: use 2026-01-31, 2026-01-31 14:30 or 01/31/2026.', $raw), ['field' => 'received_at']);
    }
}
