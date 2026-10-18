<?php

namespace App\DataImport\Application\Import;

use App\Shared\Domain\Error\InvalidValue;

/**
 * Who a row is about: the columns both files share. A row needs a phone or an email to be matched by.
 */
final readonly class ContactFields
{
    private function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phone,
        public ?string $email,
    ) {
    }

    public static function from(RowValues $values): self
    {
        $fields = new self(
            $values->text('first_name', 80),
            $values->text('last_name', 80),
            $values->phone(),
            $values->email('email'),
        );
        if (null === $fields->phone && null === $fields->email) {
            throw new InvalidValue('contact_required', 'The row needs a phone or an email.', ['field' => 'phone']);
        }

        return $fields;
    }

    /**
     * @throws InvalidValue customer_name_required, for a row that would create a customer
     */
    public function requireNames(): void
    {
        if (null === $this->firstName || null === $this->lastName) {
            throw new InvalidValue('customer_name_required', 'A new customer needs a first and a last name.', ['field' => null === $this->firstName ? 'first_name' : 'last_name']);
        }
    }
}
