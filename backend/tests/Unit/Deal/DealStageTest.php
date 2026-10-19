<?php

namespace App\Tests\Unit\Deal;

use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Model\DealStage;
use App\Deal\Domain\Model\TradeIn;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DealStageTest extends TestCase
{
    private const string DEALERSHIP = '01a150c9-0000-7000-8000-0000000000d1';
    private const string CUSTOMER = '01a150c9-0000-7000-8000-0000000000c1';

    public function testANewDealStartsInNewWithItsActivityStamped(): void
    {
        $deal = self::deal();

        self::assertSame(DealStage::New, $deal->stage());
        self::assertEquals(self::at('2026-10-01 09:00'), $deal->stageEnteredAt());
        self::assertEquals(self::at('2026-10-01 09:00'), $deal->lastActivityAt(), 'Opening a deal is its first activity.');
        self::assertTrue($deal->isOpen());
    }

    /**
     * @return iterable<string, array{DealStage, DealStage}>
     */
    public static function manualMoves(): iterable
    {
        $open = array_filter(DealStage::cases(), static fn (DealStage $s): bool => DealStage::Lost !== $s);
        foreach ($open as $from) {
            foreach ($open as $to) {
                if ($from !== $to) {
                    yield $from->value.' to '.$to->value => [$from, $to];
                }
            }
        }
    }

    #[DataProvider('manualMoves')]
    public function testEveryRoleMayMoveAnOpenDealForwardOrBack(DealStage $from, DealStage $to): void
    {
        $deal = self::deal($from);

        $previous = $deal->moveTo($to, null, self::at('2026-10-05 10:00'));

        self::assertSame($from, $previous, 'The move reports where the deal came from.');
        self::assertSame($to, $deal->stage());
        self::assertEquals(self::at('2026-10-05 10:00'), $deal->stageEnteredAt(), 'Days in stage restart on every move.');
        self::assertEquals(self::at('2026-10-05 10:00'), $deal->lastActivityAt());
    }

    #[DataProvider('stagesWithATimestamp')]
    public function testEnteringAStageStampsItsTimestamp(DealStage $stage, string $getter): void
    {
        $deal = self::deal();

        $deal->moveTo($stage, DealStage::Lost === $stage ? 'Bought elsewhere' : null, self::at('2026-10-05 10:00'));

        self::assertEquals(self::at('2026-10-05 10:00'), $deal->{$getter}(), $stage->value.' records when it was reached.');
    }

    /**
     * @return iterable<string, array{DealStage, string}>
     */
    public static function stagesWithATimestamp(): iterable
    {
        yield 'engaged' => [DealStage::Engaged, 'engagedAt'];
        yield 'appointment' => [DealStage::Appointment, 'appointmentAt'];
        yield 'visit' => [DealStage::Visit, 'visitedAt'];
        yield 'proposal' => [DealStage::Proposal, 'proposalAt'];
        yield 'sold' => [DealStage::Sold, 'soldAt'];
        yield 'delivered' => [DealStage::Delivered, 'deliveredAt'];
        yield 'lost' => [DealStage::Lost, 'lostAt'];
    }

    public function testMovingBackClearsTheTimestampsOfTheStagesLeftBehind(): void
    {
        $deal = self::deal();
        $deal->moveTo(DealStage::Engaged, null, self::at('2026-10-02 10:00'));
        $deal->moveTo(DealStage::Proposal, null, self::at('2026-10-03 10:00'));

        $deal->moveTo(DealStage::Appointment, null, self::at('2026-10-04 10:00'));

        self::assertEquals(self::at('2026-10-02 10:00'), $deal->engagedAt(), 'Earlier stages keep when they were reached.');
        self::assertEquals(self::at('2026-10-04 10:00'), $deal->appointmentAt());
        self::assertNull($deal->proposalAt(), 'The deal is no longer at proposal, so it has not reached it.');
    }

    public function testDeliveredIsNotOpen(): void
    {
        $deal = self::deal(DealStage::Sold);
        self::assertTrue($deal->isOpen(), 'A sold deal is still open until the car is delivered.');

        $deal->moveTo(DealStage::Delivered, null, self::at('2026-10-05 10:00'));

        self::assertFalse($deal->isOpen());
    }

    public function testMovingToTheSameStageIsRefused(): void
    {
        $deal = self::deal(DealStage::Engaged);

        $this->expectException(Conflict::class);
        $this->expectExceptionMessageMatches('/already/');
        $deal->moveTo(DealStage::Engaged, null, self::at('2026-10-05 10:00'));
    }

    public function testLostNeedsAReason(): void
    {
        foreach ([null, '', '   '] as $reason) {
            $deal = self::deal(DealStage::Proposal);
            try {
                $deal->moveTo(DealStage::Lost, $reason, self::at('2026-10-05 10:00'));
                self::fail('A deal cannot be lost without saying why.');
            } catch (InvalidValue $e) {
                self::assertSame('lost_reason_required', $e->errorCode());
                self::assertSame(DealStage::Proposal, $deal->stage(), 'A refused move changes nothing.');
            }
        }
    }

    public function testLostFromAnyStageKeepsTheReason(): void
    {
        foreach (DealStage::cases() as $from) {
            if (DealStage::Lost === $from) {
                continue;
            }
            $deal = self::deal($from);

            $deal->moveTo(DealStage::Lost, '  Bought elsewhere ', self::at('2026-10-05 10:00'));

            self::assertSame(DealStage::Lost, $deal->stage(), 'Lost is reachable from '.$from->value.'.');
            self::assertSame('Bought elsewhere', $deal->lostReason());
            self::assertFalse($deal->isOpen());
        }
    }

    public function testReopeningALostDealGoesToEngagedAndForgetsTheLoss(): void
    {
        $deal = self::deal(DealStage::Proposal);
        $deal->moveTo(DealStage::Lost, 'Price', self::at('2026-10-05 10:00'));

        $previous = $deal->moveTo(DealStage::Engaged, null, self::at('2026-10-07 10:00'));

        self::assertSame(DealStage::Lost, $previous);
        self::assertSame(DealStage::Engaged, $deal->stage());
        self::assertNull($deal->lostReason());
        self::assertNull($deal->lostAt());
        self::assertNull($deal->proposalAt(), 'A reopened deal starts again from engaged.');
        self::assertTrue($deal->isOpen());
    }

    public function testALostDealReopensOnlyToEngaged(): void
    {
        foreach (DealStage::cases() as $to) {
            if (\in_array($to, [DealStage::Lost, DealStage::Engaged], true)) {
                continue;
            }
            $deal = self::deal(DealStage::Lost);
            try {
                $deal->moveTo($to, null, self::at('2026-10-05 10:00'));
                self::fail('Reopening to '.$to->value.' must be refused.');
            } catch (Conflict $e) {
                self::assertSame('reopen_to_engaged', $e->errorCode());
            }
        }
    }

    public function testAutomaticAdvanceOnlyMovesForward(): void
    {
        $deal = self::deal();
        self::assertTrue($deal->advanceTo(DealStage::Appointment, self::at('2026-10-02 10:00')), 'new to appointment is forward.');
        self::assertSame(DealStage::Appointment, $deal->stage());

        self::assertFalse($deal->advanceTo(DealStage::Engaged, self::at('2026-10-03 10:00')), 'An event never moves a deal back.');
        self::assertFalse($deal->advanceTo(DealStage::Appointment, self::at('2026-10-03 10:00')), 'Nor to the stage it is in.');
        self::assertSame(DealStage::Appointment, $deal->stage());
        self::assertEquals(self::at('2026-10-02 10:00'), $deal->stageEnteredAt());

        self::assertTrue($deal->advanceTo(DealStage::Visit, self::at('2026-10-04 10:00')));
        self::assertEquals(self::at('2026-10-04 10:00'), $deal->visitedAt());
    }

    public function testAutomaticAdvanceLeavesLostAndDeliveredDealsAlone(): void
    {
        foreach ([DealStage::Lost, DealStage::Delivered] as $stage) {
            $deal = self::deal($stage);
            self::assertFalse($deal->advanceTo(DealStage::Visit, self::at('2026-10-04 10:00')), $stage->value.' is closed.');
            self::assertSame($stage, $deal->stage());
        }
    }

    public function testDaysInStageCountWholeDaysSinceTheStageWasEntered(): void
    {
        $deal = self::deal();

        self::assertSame(0, $deal->daysInStage(self::at('2026-10-01 20:00')), 'Less than a day is zero days.');
        self::assertSame(1, $deal->daysInStage(self::at('2026-10-02 09:00')));
        self::assertSame(6, $deal->daysInStage(self::at('2026-10-07 23:59')));
        self::assertSame(0, $deal->daysInStage(self::at('2026-09-30 09:00')), 'A clock behind the record never gives negative days.');

        $deal->moveTo(DealStage::Engaged, null, self::at('2026-10-07 09:00'));
        self::assertSame(0, $deal->daysInStage(self::at('2026-10-07 23:59')), 'A move restarts the count.');
    }

    public function testATradeInIsAValueOnTheDeal(): void
    {
        $deal = self::deal();

        $deal->setTradeIn(new TradeIn(2017, 'Honda', 'Civic', 82000, 450000));
        self::assertEquals(new TradeIn(2017, 'Honda', 'Civic', 82000, 450000), $deal->tradeIn());
        self::assertSame(['year' => 2017, 'make' => 'Honda', 'model' => 'Civic', 'mileage' => 82000, 'payoff_cents' => 450000], $deal->tradeInJson());

        $deal->setTradeIn(new TradeIn(null, '  ', null, null, null));
        self::assertNull($deal->tradeIn(), 'An empty trade-in is no trade-in.');
        self::assertNull($deal->tradeInJson());
    }

    public function testAVehicleOfInterestIsAnInventoryIdOrFreeText(): void
    {
        $deal = self::deal();

        $deal->attachVehicle('01a150c9-0000-7000-8000-0000000000e1', '  ');
        self::assertSame('01a150c9-0000-7000-8000-0000000000e1', $deal->vehicleId());
        self::assertNull($deal->vehicleText(), 'Blank text is no text.');

        $deal->attachVehicle(null, ' Red Mustang convertible ');
        self::assertNull($deal->vehicleId());
        self::assertSame('Red Mustang convertible', $deal->vehicleText());
    }

    private static function deal(DealStage $stage = DealStage::New): Deal
    {
        $deal = Deal::open(self::DEALERSHIP, self::CUSTOMER, self::at('2026-10-01 09:00'));
        if (DealStage::New !== $stage) {
            // Get there the way a user would; lost needs its reason.
            $deal->moveTo($stage, DealStage::Lost === $stage ? 'Bought elsewhere' : null, self::at('2026-10-01 09:00'));
        }

        return $deal;
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }
}
