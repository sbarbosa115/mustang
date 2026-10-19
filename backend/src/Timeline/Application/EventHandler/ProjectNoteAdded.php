<?php

namespace App\Timeline\Application\EventHandler;

use App\Customer\Domain\Event\NoteAdded;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;
use App\Timeline\Application\ActivityKey;
use App\Timeline\Application\Command\RecordActivity;
use App\Timeline\Domain\Model\ActivityKind;

/**
 * Runs without a signed-in user: the dealership and customer come from the event.
 */
final readonly class ProjectNoteAdded implements EventHandler
{
    public function __construct(private CommandBus $commands)
    {
    }

    public function __invoke(NoteAdded $event): void
    {
        $code = 'note_added';
        $this->commands->dispatch(new RecordActivity(
            $event->dealershipId,
            $event->customerId,
            ActivityKind::Note,
            $event->occurredAt,
            'Note added',
            $code,
            ActivityKey::of($code, NoteAdded::class, $event->noteId, $event->occurredAt),
            'note',
            $event->noteId,
            $event->authorUserId,
        ));
    }
}
