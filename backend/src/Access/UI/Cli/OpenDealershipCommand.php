<?php

namespace App\Access\UI\Cli;

use App\Access\Application\Command\OpenDealership;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Error\DomainError;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * How a production database gets its first dealership: nobody can sign in until one exists, and its admin adds
 * the rest of the team from Settings. Without --password it asks for one (hidden), or, when nobody can answer,
 * makes one and shows it once. Two-step sign-in is set up on the admin's first sign-in.
 */
#[AsCommand(name: 'mustang:dealership:open', description: 'Open a dealership with its first admin (works in production)')]
final readonly class OpenDealershipCommand
{
    private const int MIN_PASSWORD = 12;

    public function __construct(private CommandBus $commands, private PasswordHasherFactoryInterface $hashers)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Argument('The dealership\'s name, as the team sees it')] string $name,
        #[Argument('The first admin\'s email, used to sign in')] string $email,
        #[Option('The admin\'s first name')] string $firstName = '',
        #[Option('The admin\'s last name')] string $lastName = '',
        #[Option('The dealership\'s time zone')] string $timezone = 'America/New_York',
        #[Option('The admin\'s first password (asked for when left out; at least 12 characters)')] ?string $password = null,
    ): int {
        $generated = false;
        if (null === $password && $input->isInteractive()) {
            $password = (string) $io->askHidden('The admin\'s first password (leave it empty to make one)');
        }
        if (null === $password || '' === $password) {
            $password = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            $generated = true;
        }
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            $io->error(\sprintf('The password needs at least %d characters.', self::MIN_PASSWORD));

            return Command::INVALID;
        }

        $hash = $this->hashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->hash($password);
        try {
            $id = $this->commands->dispatch(new OpenDealership($name, $timezone, $email, $firstName, $lastName, $hash));
        } catch (DomainError $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Opened %s (%s). Its admin signs in as %s.', trim($name), $id, mb_strtolower(trim($email))));
        if ($generated) {
            $io->writeln(\sprintf(' Password: %s', $password));
            $io->note('It is shown only this once: hand it over in person or by phone, never by email.');
        }

        return Command::SUCCESS;
    }
}
