<?php

namespace App\Tests\Unit\Access;

use App\Access\UI\Http\Security\MfaPolicy;
use PHPUnit\Framework\TestCase;

final class MfaPolicyTest extends TestCase
{
    public function testTwoStepSignInIsRequiredUnlessSwitchedOffOutsideProduction(): void
    {
        self::assertTrue(new MfaPolicy(true, 'dev')->isRequired());
        self::assertFalse(new MfaPolicy(false, 'dev')->isRequired(), 'A developer may switch it off on their own machine.');
        self::assertFalse(new MfaPolicy(false, 'test')->isRequired());
        self::assertTrue(new MfaPolicy(false, 'prod')->isRequired(), 'Production always requires it, whatever the setting says (NFR-02).');
    }
}
