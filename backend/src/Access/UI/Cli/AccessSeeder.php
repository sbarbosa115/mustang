<?php

namespace App\Access\UI\Cli;

use App\Access\Domain\Model\Dealership;
use App\Access\Domain\Model\Role;
use App\Access\Domain\Model\User;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Two dealerships and the seed accounts. Every account's password is Mustang-dev-1.
 * Keys: dealership.mustang, dealership.other, user.<email>.
 */
final readonly class AccessSeeder implements DemoSeeder
{
    public const string PASSWORD = 'Mustang-dev-1';

    private const array USERS = [
        ['mustang', 'admin@mustang.test', 'Alex', 'Admin', Role::Admin],
        ['mustang', 'manager@mustang.test', 'Maria', 'Gonzalez', Role::Manager],
        ['mustang', 'bdc@mustang.test', 'Brianna', 'Diaz', Role::Bdc],
        ['mustang', 'sales@mustang.test', 'Sam', 'Patel', Role::Sales],
        ['mustang', 'sales2@mustang.test', 'Jordan', 'Reyes', Role::Sales],
        ['other', 'other@mustang.test', 'Olivia', 'Other', Role::Admin],
    ];

    public function __construct(private EntityManagerInterface $em, private PasswordHasherFactoryInterface $hashers)
    {
    }

    public static function priority(): int
    {
        return 1000;
    }

    public function seed(SeedContext $context): void
    {
        $mustang = new Dealership('Mustang Motors Tampa');
        $other = new Dealership('Other Motors');
        $this->em->persist($mustang);
        $this->em->persist($other);
        $context->set('dealership.mustang', $mustang->id());
        $context->set('dealership.other', $other->id());

        $hash = $this->hashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->hash(self::PASSWORD);
        foreach (self::USERS as [$dealership, $email, $first, $last, $role]) {
            $user = new User($context->id('dealership.'.$dealership), $email, $hash, $first, $last, $role);
            $this->em->persist($user);
            $context->set('user.'.$email, $user->id());
        }
    }
}
