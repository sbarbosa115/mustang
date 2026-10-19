<?php

namespace App\DataImport\Application\Import;

/**
 * One row of customers.csv.
 */
final readonly class CustomerRow
{
    public const array COLUMNS = ['first_name', 'last_name', 'phone', 'email', 'address', 'sales_rep_email', 'sms_consent', 'email_consent', 'consent_source', 'notes'];
    public const array REQUIRED = ['first_name', 'last_name', 'phone', 'email'];

    private function __construct(
        public ContactFields $contact,
        public ?string $address,
        public ?string $salesRepEmail,
        public bool $smsConsent,
        public bool $emailConsent,
        public ?string $consentSource,
        public ?string $notes,
    ) {
    }

    /**
     * @param array<string, string> $values
     *
     * @throws \App\Shared\Domain\Error\InvalidValue with the column in detail.field
     */
    public static function fromArray(array $values): self
    {
        $cells = new RowValues($values);
        $contact = ContactFields::from($cells);
        $row = new self(
            $contact,
            $cells->text('address', 500),
            $cells->email('sales_rep_email'),
            $cells->yesNo('sms_consent'),
            $cells->yesNo('email_consent'),
            $cells->clipped('consent_source', 120),
            $cells->text('notes', 5000),
        );
        if ($row->smsConsent && null === $contact->phone) {
            throw new \App\Shared\Domain\Error\InvalidValue('consent_without_contact', 'sms_consent is yes but the row has no phone.', ['field' => 'sms_consent']);
        }
        if ($row->emailConsent && null === $contact->email) {
            throw new \App\Shared\Domain\Error\InvalidValue('consent_without_contact', 'email_consent is yes but the row has no email.', ['field' => 'email_consent']);
        }

        return $row;
    }

    /**
     * The note the row adds: its notes, then its address (customers have no address field).
     */
    public function noteBody(): ?string
    {
        $parts = array_filter([$this->notes, null === $this->address ? null : 'Address: '.$this->address]);

        return [] === $parts ? null : implode("\n", $parts);
    }
}
