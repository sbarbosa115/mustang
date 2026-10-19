<?php

namespace App\Audit\Infrastructure\Console;

use App\Audit\Infrastructure\Doctrine\ChangeRecorder;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * mustang:seed-demo writes made-up data that no person did: recording it would bury the real history under thousands
 * of entries without an actor (and slow the seed of every test run). The log starts after the seed.
 */
final readonly class PauseRecordingWhileSeeding
{
    private const string SEED_COMMAND = 'mustang:seed-demo';

    public function __construct(private ChangeRecorder $recorder)
    {
    }

    #[AsEventListener(event: ConsoleEvents::COMMAND)]
    public function onCommand(ConsoleCommandEvent $event): void
    {
        if (self::SEED_COMMAND === $event->getCommand()?->getName()) {
            $this->recorder->pause();
        }
    }

    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        if (self::SEED_COMMAND === $event->getCommand()?->getName()) {
            $this->recorder->resume();
        }
    }
}
