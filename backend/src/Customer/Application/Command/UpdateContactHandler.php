<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\Conflict;

final readonly class UpdateContactHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    public function __invoke(UpdateContact $command): void
    {
        $customer = $this->customers->get($command->customerId);
        $contact = $customer->contact($command->contactId);
        $value = null === $command->value || '' === trim($command->value) ? null : $contact->kind()->normalize($command->value);
        if (null !== $value) {
            $owner = $this->customers->findByContact($contact->kind(), $value, $customer->id());
            if (null !== $owner) {
                throw new Conflict('duplicate_contact', 'Another customer already has this contact.', ['customer_id' => $owner->id(), 'kind' => $contact->kind()->value]);
            }
        }
        $customer->changeContact($command->contactId, $value, $command->label, $command->isPrimary);
        $customer->touch();
    }
}
