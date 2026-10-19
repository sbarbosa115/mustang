<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Event\NoteAdded;
use App\Customer\Domain\Model\CustomerNote;
use App\Customer\Domain\Repository\CustomerNoteRepository;
use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Domain\Error\InvalidValue;

final readonly class AddNoteHandler implements CommandHandler
{
    public function __construct(
        private CustomerRepository $customers,
        private CustomerNoteRepository $notes,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(AddNote $command): string
    {
        $customer = $this->customers->get($command->customerId);
        $body = trim($command->body);
        if ('' === $body) {
            throw new InvalidValue('invalid_note', 'A note needs some text.');
        }
        $note = new CustomerNote($customer->dealershipId(), $customer->id(), $command->authorUserId, $body);
        $this->notes->add($note);
        $customer->touch();
        $this->events->publish(new NoteAdded($note->id(), $customer->dealershipId(), $customer->id(), $command->authorUserId, $this->clock->now()));

        return $note->id();
    }
}
