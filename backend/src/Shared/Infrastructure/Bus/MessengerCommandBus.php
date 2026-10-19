<?php

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\DomainError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerCommandBus implements CommandBus
{
    use HandleTrait;

    public function __construct(#[Autowire(service: 'command.bus')] MessageBusInterface $commandBus)
    {
        $this->messageBus = $commandBus;
    }

    public function dispatch(object $command): mixed
    {
        try {
            return $this->handle($command);
        } catch (HandlerFailedException $e) {
            // Unwrap so a DomainError reaches the API subscriber as itself.
            $previous = $e->getPrevious();
            if ($previous instanceof DomainError) {
                throw $previous;
            }
            throw $e;
        }
    }
}
