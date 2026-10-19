<?php

namespace App\Access\Application\Command;

use App\Access\Domain\Error\EmailTaken;
use App\Access\Domain\Model\Dealership;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Access\Domain\Repository\DealershipRepository;
use App\Access\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\InvalidValue;

final readonly class OpenDealershipHandler implements CommandHandler
{
    public function __construct(private DealershipRepository $dealerships, private UserRepository $users)
    {
    }

    public function __invoke(OpenDealership $command): string
    {
        $name = trim($command->name);
        $first = trim($command->adminFirstName);
        $last = trim($command->adminLastName);
        $email = mb_strtolower(trim($command->adminEmail));
        if ('' === $name) {
            throw new InvalidValue('invalid_name', 'A dealership needs a name.');
        }
        if ('' === $first || '' === $last) {
            throw new InvalidValue('invalid_name', 'The admin needs a first and a last name.');
        }
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidValue('invalid_email', 'The admin\'s email is not a valid email address.');
        }
        if (!\in_array($command->timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidValue('invalid_timezone', 'Unknown time zone: use one such as America/New_York.');
        }
        if ($this->users->emailExists($email)) {
            throw new EmailTaken();
        }

        $dealership = new Dealership($name, $command->timezone);
        $this->dealerships->add($dealership);
        $this->users->add(new User($dealership->id(), $email, $command->passwordHash, $first, $last, Role::Admin));

        return $dealership->id();
    }
}
