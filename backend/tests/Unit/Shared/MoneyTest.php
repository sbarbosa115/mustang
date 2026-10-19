<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testReadsDollarsIntoCents(): void
    {
        self::assertSame(2499900, Money::fromDecimal('$24,999')->cents);
        self::assertSame(1050, Money::fromDecimal('10.5')->cents, 'One decimal digit means tenths of a dollar.');
        self::assertSame(-125, Money::fromDecimal('-1.25')->cents);
    }

    public function testAddsWithoutFloatingPointDrift(): void
    {
        self::assertSame(30, Money::ofCents(10)->add(Money::ofCents(20))->cents);
    }

    public function testRefusesThreeDecimals(): void
    {
        $this->expectException(InvalidValue::class);
        Money::fromDecimal('1.005');
    }
}
