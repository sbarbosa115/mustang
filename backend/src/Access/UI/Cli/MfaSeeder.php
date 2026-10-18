<?php

namespace App\Access\UI\Cli;

use App\Access\Domain\Model\MfaCredential;
use App\Access\Domain\Model\User;
use App\Shared\Application\Port\Encrypter;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Every seed user is enrolled with the same TOTP secret and the same eight recovery codes, so the smoke suite and a
 * developer can finish sign-in (dev and test only; mustang:seed-demo refuses to run in prod).
 */
final readonly class MfaSeeder implements DemoSeeder
{
    public const string SECRET = 'MUSTANGDEVTOTPSECRET234567';
    public const array RECOVERY_CODES = [
        'MUSTANG-RECOVERY-1', 'MUSTANG-RECOVERY-2', 'MUSTANG-RECOVERY-3', 'MUSTANG-RECOVERY-4',
        'MUSTANG-RECOVERY-5', 'MUSTANG-RECOVERY-6', 'MUSTANG-RECOVERY-7', 'MUSTANG-RECOVERY-8',
    ];

    public function __construct(private EntityManagerInterface $em, private Encrypter $encrypter)
    {
    }

    public static function priority(): int
    {
        // Right after AccessSeeder (1000), which creates the users.
        return 990;
    }

    public function seed(SeedContext $context): void
    {
        /** @var list<User> $users */
        $users = $this->em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $credential = new MfaCredential($user->dealershipId(), $user->id(), $this->encrypter->encrypt(self::SECRET));
            // Step 0: no code has been used yet, so the first sign-in of a run accepts the current one.
            $credential->confirm($context->now, 0, self::RECOVERY_CODES);
            $this->em->persist($credential);
        }
    }
}
