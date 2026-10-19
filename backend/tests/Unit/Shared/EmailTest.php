<?php

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Email;
use App\Shared\Domain\Error\InvalidValue;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testTrimsAndLowercases(): void
    {
        self::assertSame('sarah.johnson@example.com', Email::fromString('  Sarah.Johnson@Example.COM ')->value, 'De-duplication compares lowercase addresses.');
    }

    public function testRefusesAnInvalidAddress(): void
    {
        $this->expectException(InvalidValue::class);
        Email::fromString('sarah@');
    }
}
