<?php

namespace App\Tests\Unit\Appointment;

use App\Appointment\Domain\Error\AppointmentNotOpen;
use App\Appointment\Domain\Model\Appointment;
use App\Appointment\Domain\Model\AppointmentStatus;
use App\Shared\Domain\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppointmentTest extends TestCase
{
    private const string START = '2026-11-03 15:00:00';

    private function appointment(int $minutes = 60): Appointment
    {
        return new Appointment(Id::new(), Id::new(), Id::new(), new \DateTimeImmutable(self::START, new \DateTimeZone('UTC')), $minutes);
    }

    private function at(string $when): \DateTimeImmutable
    {
        return new \DateTimeImmutable($when, new \DateTimeZone('UTC'));
    }

    public function testItEndsWhenItsLengthIsOver(): void
    {
        self::assertSame('2026-11-03 16:30:00', $this->appointment(90)->endsAt()->format('Y-m-d H:i:s'));
    }

    public function testConfirmingKeepsTheFirstConfirmationTime(): void
    {
        $appointment = $this->appointment();

        $appointment->confirm($this->at('2026-11-01 09:00:00'));
        $appointment->confirm($this->at('2026-11-02 09:00:00'));

        self::assertSame(AppointmentStatus::Confirmed, $appointment->status());
        self::assertSame('2026-11-01 09:00:00', $appointment->confirmedAt()?->format('Y-m-d H:i:s'), 'Confirming twice is harmless and keeps when the customer first confirmed.');
    }

    public function testShownIsRecordedWithItsTime(): void
    {
        $appointment = $this->appointment();

        $appointment->markShown($this->at('2026-11-03 15:05:00'));

        self::assertSame(AppointmentStatus::Shown, $appointment->status());
        self::assertSame('2026-11-03 15:05:00', $appointment->shownAt()?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function closingMoves(): iterable
    {
        yield 'show' => ['markShown'];
        yield 'no-show' => ['markNoShow'];
        yield 'cancel' => ['cancel'];
    }

    #[DataProvider('closingMoves')]
    public function testAnAppointmentThatIsOverCannotBeChangedAgain(string $closing): void
    {
        $appointment = $this->appointment();
        $this->close($appointment, $closing);

        foreach (['confirm', 'markShown', 'markNoShow', 'cancel'] as $move) {
            try {
                $this->close($appointment, $move);
                self::fail("$move must be refused after $closing.");
            } catch (AppointmentNotOpen $e) {
                self::assertSame('appointment_not_open', $e->errorCode());
                self::assertSame(409, $e->httpStatus());
            }
        }
        $this->expectException(AppointmentNotOpen::class);
        $appointment->moveTo($this->at('2026-12-01 10:00:00'));
    }

    public function testMovingItAsksForANewConfirmationAndANewReminder(): void
    {
        $appointment = $this->appointment();
        $appointment->confirm($this->at('2026-11-01 09:00:00'));
        $appointment->markReminderSent($this->at('2026-11-03 14:00:00'));

        $appointment->moveTo($this->at('2026-11-04 10:00:00'));

        self::assertSame(AppointmentStatus::Scheduled, $appointment->status(), 'The customer agreed to another time.');
        self::assertNull($appointment->confirmedAt());
        self::assertNull($appointment->reminderSentAt(), 'The rep is reminded again for the new time.');
        self::assertSame('2026-11-04 10:00:00', $appointment->scheduledAt()->format('Y-m-d H:i:s'));
    }

    public function testCancelledAndMissedAppointmentsDoNotTakeTheRepsTime(): void
    {
        $cancelled = $this->appointment();
        $cancelled->cancel();
        $missed = $this->appointment();
        $missed->markNoShow();
        $shown = $this->appointment();
        $shown->markShown($this->at(self::START));

        self::assertFalse($cancelled->occupiesRep());
        self::assertFalse($missed->occupiesRep());
        self::assertTrue($shown->occupiesRep(), 'A visit that happened did take the rep\'s time.');
        self::assertTrue($this->appointment()->occupiesRep());
    }

    public function testAReminderQueuedForAnOldTimeIsNotNeeded(): void
    {
        $appointment = $this->appointment();
        $queuedFor = $appointment->scheduledAt();

        self::assertTrue($appointment->needsReminderFor($queuedFor));

        $appointment->moveTo($this->at('2026-11-04 10:00:00'));
        self::assertFalse($appointment->needsReminderFor($queuedFor), 'The message queued for the old time is stale.');
        self::assertTrue($appointment->needsReminderFor($this->at('2026-11-04 10:00:00')));

        $appointment->markReminderSent($this->at('2026-11-04 09:00:00'));
        self::assertFalse($appointment->needsReminderFor($this->at('2026-11-04 10:00:00')), 'Reminded once is enough.');
    }

    public function testNoReminderForAnAppointmentThatIsCancelled(): void
    {
        $appointment = $this->appointment();
        $appointment->cancel();

        self::assertFalse($appointment->needsReminderFor($appointment->scheduledAt()));
    }

    public function testEditingLengthKindAndNote(): void
    {
        $appointment = $this->appointment();

        $appointment->describe(30, \App\Appointment\Domain\Model\AppointmentKind::TestDrive, '  Bring the trade-in  ');
        self::assertSame([30, 'test_drive', 'Bring the trade-in'], [$appointment->durationMinutes(), $appointment->kind()->value, $appointment->note()]);

        $appointment->describe(null, null, '   ');
        self::assertNull($appointment->note(), 'A blank note clears it.');
        self::assertSame(30, $appointment->durationMinutes(), 'Left out means unchanged.');
    }

    private function close(Appointment $appointment, string $move): void
    {
        match ($move) {
            'confirm', 'markShown' => $appointment->{$move}($this->at('2026-11-03 15:05:00')),
            default => $appointment->{$move}(),
        };
    }
}
