<?php

namespace App\DataImport\Application\Import;

use App\DataImport\Application\Port\CustomerRecords;
use App\DataImport\Application\Port\CustomerSnapshot;
use App\Shared\Domain\Error\InvalidValue;

/**
 * De-duplication (PRD "Import"): a row is the customer with its phone, else the one with its email. Updating a
 * duplicate fills in what the row brings and never moves a contact from one customer to another.
 */
final readonly class CustomerMatcher
{
    public function __construct(private CustomerRecords $customers)
    {
    }

    /**
     * @throws InvalidValue contacts_on_two_customers when the phone and the email belong to different customers
     */
    public function match(ContactFields $row): ?CustomerSnapshot
    {
        $byPhone = null === $row->phone ? null : $this->customers->findByContact($row->phone, null);
        $byEmail = null === $row->email ? null : $this->customers->findByContact(null, $row->email);
        if (null !== $byPhone && null !== $byEmail && $byPhone->id !== $byEmail->id) {
            throw new InvalidValue('contacts_on_two_customers', 'The phone and the email belong to two different customers.', ['field' => 'email']);
        }

        return $byPhone ?? $byEmail;
    }

    /**
     * Renames the customer and adds the row's missing phone or email; answers whether anything changed.
     */
    public function update(CustomerSnapshot $customer, ContactFields $row): bool
    {
        $changed = false;
        $first = $row->firstName ?? $customer->firstName;
        $last = $row->lastName ?? $customer->lastName;
        if ($first !== $customer->firstName || $last !== $customer->lastName) {
            $this->customers->rename($customer->id, $first, $last);
            $changed = true;
        }
        // match() has made sure neither contact belongs to someone else.
        if (null !== $row->phone && !\in_array($row->phone, $customer->phones, true)) {
            $this->customers->addContact($customer->id, 'phone', $row->phone);
            $changed = true;
        }
        if (null !== $row->email && !\in_array($row->email, $customer->emails, true)) {
            $this->customers->addContact($customer->id, 'email', $row->email);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Adds the note unless the customer already has it, so importing a file twice leaves one copy.
     */
    public function note(string $customerId, string $authorUserId, ?string $body): bool
    {
        if (null === $body || $this->customers->hasNote($customerId, $body)) {
            return false;
        }
        $this->customers->addNote($customerId, $authorUserId, $body);

        return true;
    }
}
