<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validNumbers(): iterable
    {
        yield 'ten digits are a US number' => ['(813) 555-1001', '+18135551001'];
        yield 'eleven digits starting with 1' => ['1 813 555 1001', '+18135551001'];
        yield 'already E.164' => ['+18135551001', '+18135551001'];
        yield 'international keeps its country code' => ['+52 55 1234 5678', '+525512345678'];
        yield 'surrounding spaces' => ['  813.555.1001 ', '+18135551001'];
    }

    #[DataProvider('validNumbers')]
    public function testNormalisesToE164(string $raw, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::fromString($raw)->e164, 'Contacts are stored in E.164 so lookups and STOP matching agree.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNumbers(): iterable
    {
        yield 'too short' => ['555-1001'];
        yield 'letters' => ['call me'];
        yield 'empty' => [''];
        yield 'twelve local digits' => ['813555100199'];
    }

    #[DataProvider('invalidNumbers')]
    public function testRefusesWhatIsNotAPhoneNumber(string $raw): void
    {
        $this->expectException(InvalidValue::class);
        PhoneNumber::fromString($raw);
    }
}
