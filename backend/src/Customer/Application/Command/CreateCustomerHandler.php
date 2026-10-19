<?php

namespace App\Customer\Application\Command;

use App\Customer\Application\TeamAssignmentPolicy;
use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;

final readonly class CreateCustomerHandler implements CommandHandler
{
    public function __construct(
        private CustomerRepository $customers,
        private TenantContext $tenant,
        private TeamAssignmentPolicy $team,
    ) {
    }

    public function __invoke(CreateCustomer $command): string
    {
        $first = trim($command->firstName);
        $last = trim($command->lastName);
        if ('' === $first || '' === $last) {
            throw new InvalidValue('invalid_name', 'A customer needs a first and a last name.');
        }
        $contacts = [];
        foreach ([[ContactKind::Phone, $command->phone], [ContactKind::Email, $command->email]] as [$kind, $raw]) {
            if (null === $raw || '' === trim($raw)) {
                continue;
            }
            $value = $kind->normalize($raw);
            $existing = $this->customers->findByContact($kind, $value);
            if (null !== $existing) {
                throw new Conflict('duplicate_contact', 'Another customer already has this contact.', ['customer_id' => $existing->id(), 'kind' => $kind->value]);
            }
            $contacts[] = [$kind, $value];
        }
        $this->team->check('sales1', $command->sales1UserId);

        $customer = new Customer(
            $this->tenant->dealershipId(),
            $first,
            $last,
            $command->preferredChannel,
            $command->sales1UserId,
            source: $command->source,
        );
        foreach ($contacts as [$kind, $value]) {
            $customer->addContact($kind, $value, ContactKind::Phone === $kind ? 'mobile' : null, true);
        }
        $this->customers->add($customer);

        return $customer->id();
    }
}
