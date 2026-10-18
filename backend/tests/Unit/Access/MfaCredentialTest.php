<?php

namespace App\Tests\Unit\Access;

use App\Access\Domain\Error\MfaAlreadyEnrolled;
use App\Access\Domain\Error\MfaCodeRejected;
use App\Access\Domain\Model\MfaCredential;
use App\Access\Domain\Model\RecoveryCode;
use PHPUnit\Framework\TestCase;

final class MfaCredentialTest extends TestCase
{
    private function confirmed(): MfaCredential
    {
        $credential = new MfaCredential('d1', 'u1', 'encrypted');
        $credential->confirm(new \DateTimeImmutable('2026-01-01'), 100, ['AAAAA-BBBBB', 'CCCCC-DDDDD']);

        return $credential;
    }

    public function testANewCredentialIsPendingUntilConfirmed(): void
    {
        $credential = new MfaCredential('d1', 'u1', 'encrypted');
        self::assertFalse($credential->isConfirmed(), 'A secret nobody has proved they hold is not a factor yet.');

        $credential->confirm(new \DateTimeImmutable('2026-01-01'), 100, ['AAAAA-BBBBB']);
        self::assertTrue($credential->isConfirmed());
        self::assertSame(1, $credential->recoveryCodesRemaining());
    }

    public function testAConfirmedCredentialCannotBeConfirmedOrRestartedAgain(): void
    {
        $credential = $this->confirmed();

        $this->expectException(MfaAlreadyEnrolled::class);
        $credential->restart('other');
    }

    public function testATimeStepIsAcceptedOnlyOnce(): void
    {
        $credential = $this->confirmed();
        $credential->acceptStep(101);

        try {
            $credential->acceptStep(101);
            self::fail('The same code twice must be refused: a code seen over a shoulder is useless.');
        } catch (MfaCodeRejected $e) {
            self::assertSame('mfa_code_reused', $e->errorCode());
        }
    }

    public function testAnEarlierStepThanTheLastUsedOneIsRefused(): void
    {
        $credential = $this->confirmed();

        $this->expectException(MfaCodeRejected::class);
        $credential->acceptStep(100);
    }

    public function testARecoveryCodeWorksOnceWhateverItsCaseOrDashes(): void
    {
        $credential = $this->confirmed();
        $credential->useRecoveryCode('aaaaa bbbbb');
        self::assertSame(1, $credential->recoveryCodesRemaining());

        $this->expectException(MfaCodeRejected::class);
        $credential->useRecoveryCode('AAAAA-BBBBB');
    }

    public function testRecoveryCodesAreKeptHashed(): void
    {
        $credential = $this->confirmed();

        $stored = json_encode($credential->recoveryCodesJson(), \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('AAAAA', $stored, 'A database read must not reveal a usable code.');
        self::assertStringContainsString(RecoveryCode::hash('AAAAA-BBBBB'), $stored);
    }
}
