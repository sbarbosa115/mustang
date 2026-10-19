<?php

namespace App\DataImport\Infrastructure\Adapter;

use App\Customer\Application\Command\AddContact;
use App\Customer\Application\Command\AddNote;
use App\Customer\Application\Command\AssignTeam;
use App\Customer\Application\Command\CreateCustomer;
use App\Customer\Application\Command\UpdateCustomer;
use App\Customer\Application\Query\CustomerQueries;
use App\Customer\Application\Query\NoteQueries;
use App\Customer\Domain\Model\ContactKind;
use App\DataImport\Application\Port\CustomerRecords;
use App\DataImport\Application\Port\CustomerSnapshot;
use App\Shared\Application\Bus\CommandBus;

/**
 * Through the Customer context's commands (each in its own savepoint of the import's transaction) and queries.
 */
final readonly class CustomerRecordsAdapter implements CustomerRecords
{
    public function __construct(
        private CommandBus $commands,
        private CustomerQueries $customers,
        private NoteQueries $notes,
    ) {
    }

    public function findByContact(?string $phone, ?string $email): ?CustomerSnapshot
    {
        $found = $this->customers->findByContact($phone, null === $phone ? $email : null);
        if ([] === $found) {
            return null;
        }
        $customer = $found[0];
        $phones = $emails = [];
        foreach ($customer->contacts() as $contact) {
            if ('phone' === $contact->kind()->value) {
                $phones[] = $contact->value();
            } else {
                $emails[] = $contact->value();
            }
        }

        return new CustomerSnapshot(
            $customer->id(),
            $customer->firstName(),
            $customer->lastName(),
            $phones,
            $emails,
            $customer->sales1UserId(),
            $customer->sales2UserId(),
            $customer->bdcUserId(),
        );
    }

    public function create(string $firstName, string $lastName, ?string $phone, ?string $email, ?string $sales1UserId, string $source): string
    {
        return (string) $this->commands->dispatch(new CreateCustomer($firstName, $lastName, $phone, $email, sales1UserId: $sales1UserId, source: $source));
    }

    public function rename(string $customerId, string $firstName, string $lastName): void
    {
        $this->commands->dispatch(new UpdateCustomer($customerId, $firstName, $lastName));
    }

    public function addContact(string $customerId, string $kind, string $value): void
    {
        $this->commands->dispatch(new AddContact($customerId, ContactKind::from($kind), $value, 'phone' === $kind ? 'mobile' : null));
    }

    public function assignTeam(string $customerId, ?string $sales1UserId, ?string $sales2UserId, ?string $bdcUserId): void
    {
        $this->commands->dispatch(new AssignTeam($customerId, $sales1UserId, $sales2UserId, $bdcUserId));
    }

    public function hasNote(string $customerId, string $body): bool
    {
        foreach ($this->notes->forCustomer($customerId) as $note) {
            if ($note->body() === $body) {
                return true;
            }
        }

        return false;
    }

    public function addNote(string $customerId, string $authorUserId, string $body): void
    {
        $this->commands->dispatch(new AddNote($customerId, $authorUserId, $body));
    }
}
