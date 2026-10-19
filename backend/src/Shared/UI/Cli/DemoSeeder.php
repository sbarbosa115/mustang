<?php

namespace App\Shared\UI\Cli;

/**
 * One area's demo data. mustang:seed-demo runs every seeder by priority (higher first); each item adds its own.
 */
interface DemoSeeder
{
    public static function priority(): int;

    public function seed(SeedContext $context): void;
}
