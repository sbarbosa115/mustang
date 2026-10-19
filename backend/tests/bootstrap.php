<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// The test database is migrated and seeded once per run; DAMA then rolls every test back to this state.
if ('test' === ($_SERVER['APP_ENV'] ?? null) && !getenv('MUSTANG_SKIP_TEST_SEED')) {
    $console = escapeshellarg(dirname(__DIR__).'/bin/console');
    passthru("php $console doctrine:database:create --if-not-exists --env=test -q", $code);
    passthru("php $console doctrine:migrations:migrate -n --allow-no-migration --env=test -q", $code);
    $kernel = new App\Kernel('test', false);
    $kernel->boot();
    /** @var Doctrine\DBAL\Connection $connection */
    $connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');
    if (0 === (int) $connection->fetchOne('SELECT COUNT(*) FROM dealerships')) {
        passthru("php $console mustang:seed-demo --env=test -q", $code);
        if (0 !== $code) {
            throw new RuntimeException('Seeding the test database failed.');
        }
    }
    $kernel->shutdown();
}
