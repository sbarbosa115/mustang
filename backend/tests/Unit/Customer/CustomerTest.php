<?php

namespace App\Tests\Unit\Customer;

use App\Customer\Domain\Model\ContactKind;
use App\Customer\Domain\Model\Customer;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\Error\NotFound;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    private function customer(): Customer
    {
        return new Customer('d1', 'Sarah', 'Johnson');
    }

    public function testTheFirstContactOfAKindIsPrimaryAndALaterOneCanTakeOver(): void
    {
        $customer = $this->customer();
        $first = $customer->addContact(ContactKind::Phone, '+18135551001');
        $second = $customer->addContact(ContactKind::Phone, '+18135551002');
        $email = $customer->addContact(ContactKind::Email, 'a@example.com');

        self::assertTrue($first->isPrimary());
        self::assertFalse($second->isPrimary());
        self::assertTrue($email->isPrimary(), 'Each kind has its own primary.');

        $customer->changeContact($second->id(), null, null, true);

        self::assertFalse($first->isPrimary());
        self::assertTrue($second->isPrimary());
        self::assertTrue($email->isPrimary());
    }

    public function testAContactCannotBeAddedTwiceToTheSameCustomer(): void
    {
        $customer = $this->customer();
        $customer->addContact(ContactKind::Email, 'a@example.com');

        $this->expectException(Conflict::class);
        $customer->addContact(ContactKind::Email, 'a@example.com');
    }

    public function testRemovingThePrimaryPromotesTheNextOfTheSameKind(): void
    {
        $customer = $this->customer();
        $first = $customer->addContact(ContactKind::Phone, '+18135551001');
        $second = $customer->addContact(ContactKind::Phone, '+18135551002');

        $customer->removeContact($first->id());

        self::assertTrue($second->isPrimary());
        self::assertCount(1, $customer->contacts());
    }

    public function testAnUnknownContactIsNotFound(): void
    {
        $this->expectException(NotFound::class);
        $this->customer()->removeContact('nope');
    }

    public function testRenameKeepsWhatIsNotSentAndRefusesBlank(): void
    {
        $customer = $this->customer();
        $customer->rename('  Sara ', null);
        self::assertSame('Sara', $customer->firstName());
        self::assertSame('Johnson', $customer->lastName());

        $this->expectException(InvalidValue::class);
        $customer->rename(null, '   ');
    }

    public function testContactKindsNormaliseTheirValues(): void
    {
        self::assertSame('+18135551001', ContactKind::Phone->normalize('(813) 555-1001'));
        self::assertSame('a@example.com', ContactKind::Email->normalize(' A@Example.com '));
    }

    public function testAnOwnedVehicleNeedsAYearMakeModelOrVin(): void
    {
        $customer = $this->customer();
        $customer->addOwnedVehicle(2020, ' Ford ', null, null, 'abc', null);
        self::assertSame('Ford', $customer->ownedVehicles()->first()->make());

        $this->expectException(InvalidValue::class);
        $customer->addOwnedVehicle(null, ' ', null, 'SE', null, null);
    }

    public function testArchivingKeepsTheFirstDate(): void
    {
        $customer = $this->customer();
        $first = new \DateTimeImmutable('2026-01-01');
        $customer->archive($first);
        $customer->archive(new \DateTimeImmutable('2026-02-01'));

        self::assertSame($first, $customer->archivedAt());
    }
}
