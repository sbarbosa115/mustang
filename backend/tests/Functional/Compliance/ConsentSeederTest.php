<?php

namespace App\Tests\Functional\Compliance;

use App\Compliance\UI\Cli\ConsentSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

final class ConsentSeederTest extends ComplianceTestCase
{
    public function testTheDemoLedgerGivesTheInboxThreadsConsentAndOneOptOut(): void
    {
        $this->signInAs('sales');
        $context = new SeedContext(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $context->set('dealership.mustang', $this->dealershipId());
        foreach (['sales', 'bdc'] as $role) {
            $context->set('user.'.self::ACCOUNTS[$role], $this->userId($role));
        }
        for ($n = 21; $n <= 28; ++$n) {
            $this->withACleanLedger($this->customerId($n));
            $context->set('customer.'.$n, $this->customerId($n));
        }

        static::getContainer()->get(ConsentSeeder::class)->seed($context);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertSame('granted', $this->ledger($this->customerId(21))['summary']['text'], 'The fumbled thread can be answered.');
        self::assertSame('granted', $this->ledger($this->customerId(24))['summary']['email']);
        self::assertSame('opted_out', $this->ledger($this->customerId(25))['summary']['text'], 'Diego Clark texted STOP.');
        self::assertSame(['text' => 'granted', 'email' => 'granted', 'call' => 'missing'], $this->ledger($this->customerId(26))['summary']);
    }
}
