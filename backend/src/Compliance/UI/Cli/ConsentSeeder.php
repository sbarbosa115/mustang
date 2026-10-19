<?php

namespace App\Compliance\UI\Cli;

use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Model\ConsentState;
use App\Compliance\Domain\Model\Suppression;
use App\Shared\Domain\Channel;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A consent ledger for customers 21-28 of Mustang Motors (the inbox threads and a few more); customers 1-20 stay
 * without consent, as the functional tests and the smoke suite expect. Customer 25 texted STOP: revoked and suppressed.
 */
final readonly class ConsentSeeder implements DemoSeeder
{
    private const string WORDING = 'I agree to receive texts from Mustang Motors about my vehicle search. Msg & data rates may apply. Reply STOP to opt out.';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 650;
    }

    public function seed(SeedContext $context): void
    {
        $sales = $context->id('user.sales@mustang.test');
        $bdc = $context->id('user.bdc@mustang.test');
        $now = $context->now;

        // The inbox threads: texts for 21-23, email for 24.
        foreach ([21, 22, 23] as $n) {
            $this->grant($context, $n, Channel::Text, 'website', $now->modify('-10 days'), null, 'Website lead form', self::WORDING, 'Fall sales event');
        }
        $this->grant($context, 22, Channel::Call, 'verbal', $now->modify('-1 day'), $sales, 'Phone call');
        $this->grant($context, 24, Channel::Email, 'written', $now->modify('-3 days'), $sales, 'Showroom desk', 'Email me quotes and updates.');
        $this->grant($context, 26, Channel::Text, 'verbal', $now->modify('-2 days'), $bdc, 'Phone call', 'Customer agreed on the phone to receive texts.');
        $this->grant($context, 26, Channel::Email, 'website', $now->modify('-2 days'), null, 'Website lead form', 'Send me offers by email.');
        $this->grant($context, 27, Channel::Text, 'import', $now->modify('-30 days'), null, null, 'Imported from the dealer\'s previous CRM');
        $this->grant($context, 28, Channel::Email, 'written', $now->modify('-5 days'), $sales, 'Showroom desk', 'Email me quotes and updates.', 'Trade-in appraisal');

        // Diego Clark (25) agreed on the website, then replied STOP.
        $phone = $this->phone(25);
        $stopped = $this->grant($context, 25, Channel::Text, 'website', $now->modify('-20 days'), null, 'Website lead form', self::WORDING);
        $stopped->revoke($now->modify('-6 days'), 'stop_keyword');
        $this->em->persist(new Suppression($phone, Channel::Text, 'stop_keyword'));
    }

    private function grant(SeedContext $context, int $n, Channel $channel, string $source, \DateTimeImmutable $at, ?string $by, ?string $where, ?string $wording = null, ?string $campaign = null): Consent
    {
        $consent = new Consent(
            $context->id('dealership.mustang'),
            $context->id('customer.'.$n),
            Channel::Email === $channel ? $this->email($n) : $this->phone($n),
            $channel,
            ConsentState::Granted,
            $source,
            $at,
            $by,
            $where,
            $wording,
            $campaign,
        );
        $this->em->persist($consent);

        return $consent;
    }

    private function phone(int $n): string
    {
        return \sprintf('+1813555%04d', 1000 + $n);
    }

    private function email(int $n): string
    {
        $names = [24 => 'sofia.sanchez', 26 => 'valentina.ramirez', 28 => 'camila.robinson'];

        return ($names[$n] ?? throw new \LogicException('No seeded email for customer '.$n)).'@example.com';
    }
}
