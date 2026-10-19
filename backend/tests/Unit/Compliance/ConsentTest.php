<?php

namespace App\Tests\Unit\Compliance;

use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Model\ConsentState;
use App\Compliance\Domain\Model\SmsKeyword;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Error\Conflict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsentTest extends TestCase
{
    private const string DEALERSHIP = '01a150c9-0000-7000-8000-0000000000d1';
    private const string CUSTOMER = '01a150c9-0000-7000-8000-0000000000c1';

    public function testAGrantedConsentIsRevokedWithItsTimeAndReason(): void
    {
        $consent = $this->granted();
        $at = new \DateTimeImmutable('2026-10-18 15:00:00');

        $consent->revoke($at, 'Customer asked by phone');

        self::assertSame(ConsentState::Revoked, $consent->state());
        self::assertFalse($consent->isGranted());
        self::assertEquals($at, $consent->revokedAt());
        self::assertSame('Customer asked by phone', $consent->revokedReason());
        self::assertEquals($at, $consent->effectiveAt(), 'A revoked row counts from the moment it was revoked.');
    }

    public function testRevokingTwiceIsAConflict(): void
    {
        $consent = $this->granted();
        $consent->revoke(new \DateTimeImmutable('2026-10-18 15:00:00'), null);

        $this->expectException(Conflict::class);
        $consent->revoke(new \DateTimeImmutable('2026-10-18 16:00:00'), null);
    }

    public function testAGrantedRowCountsFromWhenItWasCaptured(): void
    {
        $consent = $this->granted();

        self::assertTrue($consent->isGranted());
        self::assertEquals(new \DateTimeImmutable('2026-10-01 10:00:00'), $consent->effectiveAt());
    }

    /**
     * @return iterable<string, array{0: string, 1: ?SmsKeyword}>
     */
    public static function messages(): iterable
    {
        foreach (['STOP', 'stop', ' Stop ', 'STOPALL', 'unsubscribe', 'Cancel', 'END', 'quit', 'STOP.', 'stop!'] as $body) {
            yield $body => [$body, SmsKeyword::Stop];
        }
        foreach (['START', 'start', 'Unstop', ' UNSTOP '] as $body) {
            yield $body => [$body, SmsKeyword::Start];
        }
        foreach (['Please stop texting me', 'stop it', 'I want to cancel my appointment', 'starting tomorrow', '', 'STOP STOP'] as $body) {
            yield 'not a keyword: '.$body => [$body, null];
        }
    }

    #[DataProvider('messages')]
    public function testOnlyAWholeMessageKeywordCounts(string $body, ?SmsKeyword $expected): void
    {
        self::assertSame($expected, SmsKeyword::fromMessage($body), 'Keywords are case-insensitive and must be the whole message.');
    }

    private function granted(): Consent
    {
        return new Consent(
            self::DEALERSHIP,
            self::CUSTOMER,
            '+18135551001',
            Channel::Text,
            ConsentState::Granted,
            'verbal',
            new \DateTimeImmutable('2026-10-01 10:00:00'),
        );
    }
}
