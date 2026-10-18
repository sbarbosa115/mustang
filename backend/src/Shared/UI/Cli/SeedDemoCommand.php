<?php

namespace App\Shared\UI\Cli;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'mustang:seed-demo', description: 'Fill the database with the demo dealerships, users and data (dev and test only)')]
final readonly class SeedDemoCommand
{
    /**
     * @param iterable<DemoSeeder> $seeders
     */
    public function __construct(
        private iterable $seeders,
        private EntityManagerInterface $em,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Drop the schema and run the migrations first')] bool $reset = false): int
    {
        if ('prod' === $this->environment) {
            $io->error('The demo data is never seeded in production.');

            return Command::FAILURE;
        }
        if ($reset) {
            $connection = $this->em->getConnection();
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($connection->createSchemaManager()->listTableNames() as $table) {
                $connection->executeStatement('DROP TABLE `'.$table.'`');
            }
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $io->note('Dropped every table; run doctrine:migrations:migrate before seeding.');

            return Command::SUCCESS;
        }

        $context = new SeedContext(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        foreach ($this->seeders as $seeder) {
            $seeder->seed($context);
            $this->em->flush();
            $this->em->clear();
            $io->writeln(\sprintf(' <info>✓</info> %s', $seeder::class));
        }
        $io->success('Demo data seeded. Sign in as admin@mustang.test with Mustang-dev-1.');

        return Command::SUCCESS;
    }
}
