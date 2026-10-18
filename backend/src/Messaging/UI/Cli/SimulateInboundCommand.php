<?php

namespace App\Messaging\UI\Cli;

use App\Messaging\Application\Command\InboundResult;
use App\Messaging\Application\Command\ReceiveInboundMessage;
use App\Messaging\Application\Command\ReceiveInboundMessageHandler;
use App\Messaging\Application\Port\ActingUser;
use App\Messaging\Application\Port\CustomerContacts;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Error\DomainError;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A customer's reply without a carrier: the same command the dev endpoint dispatches. The dealership is the one whose
 * customer has that phone or email (pass --dealership when several do, or when none does: the unknown contact then
 * becomes a new customer with a lead). Nobody is signed in on the console, so it acts as the dealership's first active
 * admin for the length of the command.
 */
#[AsCommand(name: 'mustang:messaging:simulate-inbound', description: 'Simulate a customer reply by text or email (dev and test only)')]
final readonly class SimulateInboundCommand
{
    public function __construct(
        private CommandBus $commands,
        private CustomerContacts $contacts,
        private ActingUser $actingUser,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The customer\'s phone (any format) or email')] string $from,
        #[Argument('What the customer wrote')] string $body,
        #[Option('text or email')] string $channel = 'text',
        #[Option('Subject of an email reply')] ?string $subject = null,
        #[Option('Dealership id, when several dealerships have this contact')] ?string $dealership = null,
    ): int {
        if ('prod' === $this->environment) {
            $io->error('Replies are only simulated in dev and test.');

            return Command::FAILURE;
        }
        $kind = Channel::tryFrom($channel);
        if (Channel::Text !== $kind && Channel::Email !== $kind) {
            $io->error('The channel is "text" or "email".');

            return Command::INVALID;
        }

        try {
            [$contactKind, $value] = ReceiveInboundMessageHandler::contact($kind, $from);
            $dealerships = null === $dealership ? $this->contacts->dealershipsWith($contactKind, $value) : [$dealership];
            if ([] === $dealerships) {
                $io->error(\sprintf('No customer has %s %s; pass --dealership=<id> to receive it as a new customer with a lead.', $contactKind, $value));

                return Command::FAILURE;
            }
            if (\count($dealerships) > 1) {
                $io->error(\sprintf('Customers of several dealerships have %s; pass --dealership=<id> (%s).', $value, implode(', ', $dealerships)));

                return Command::FAILURE;
            }
            /** @var InboundResult $result */
            $result = $this->actingUser->runAsAdminOf(
                $dealerships[0],
                fn (): mixed => $this->commands->dispatch(new ReceiveInboundMessage($dealerships[0], $kind, $from, $body, $subject)),
            );
        } catch (DomainError $e) {
            $io->error(\sprintf('%s (%s)', $e->getMessage(), $e->errorCode()));

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Reply from %s (%s) added to conversation %s, message %s.',
            $this->contacts->nameOf($dealerships[0], $result->customerId),
            $value,
            $result->conversationId,
            $result->messageId,
        ));

        return Command::SUCCESS;
    }
}
