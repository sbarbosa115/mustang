<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\Conflict;

final readonly class AddContactHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    public function __invoke(AddContact $command): string
    {
        $customer = $this->customers->get($command->customerId);
        $value = $command->kind->normalize($command->value);
        $owner = $this->customers->findByContact($command->kind, $value, $customer->id());
        if (null !== $owner) {
            throw new Conflict('duplicate_contact', 'Another customer already has this contact.', ['customer_id' => $owner->id(), 'kind' => $command->kind->value]);
        }
        $contact = $customer->addContact($command->kind, $value, $command->label, $command->isPrimary);
        $customer->touch();

        return $contact->id();
    }
}
