<?php

namespace App\Appointment\Domain\Model;

use App\Appointment\Domain\Error\AppointmentNotOpen;
use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A booked visit, test drive or delivery, stored in UTC (CRM-05).
 */
#[ORM\Entity]
#[ORM\Table(name: 'appointments')]
#[ORM\Index(name: 'idx_appointments_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_appointments_when', columns: ['dealership_id', 'scheduled_at'])]
#[ORM\Index(name: 'idx_appointments_user', columns: ['dealership_id', 'user_id', 'scheduled_at'])]
#[ORM\HasLifecycleCallbacks]
class Appointment
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'customer_id')]
    private string $customerId;
    #[ORM\Column(type: 'guid', name: 'user_id')]
    private string $userId;
    #[ORM\Column(type: 'datetime_immutable', name: 'scheduled_at')]
    private \DateTimeImmutable $scheduledAt;
    #[ORM\Column(type: 'integer', name: 'duration_minutes')]
    private int $durationMinutes;
    #[ORM\Column(type: 'string', length: 20, enumType: AppointmentKind::class, name: 'kind')]
    private AppointmentKind $kind;
    #[ORM\Column(type: 'string', length: 20, enumType: AppointmentStatus::class, name: 'status')]
    private AppointmentStatus $status;
    #[ORM\Column(type: 'guid', name: 'deal_id', nullable: true)]
    private ?string $dealId;
    #[ORM\Column(type: 'datetime_immutable', name: 'confirmed_at', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt;
    #[ORM\Column(type: 'datetime_immutable', name: 'shown_at', nullable: true)]
    private ?\DateTimeImmutable $shownAt;
    #[ORM\Column(type: 'text', name: 'note', nullable: true)]
    private ?string $note;
    #[ORM\Column(type: 'datetime_immutable', name: 'reminder_sent_at', nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt;

    public function __construct(
        string $dealershipId,
        string $customerId,
        string $userId,
        \DateTimeImmutable $scheduledAt,
        int $durationMinutes = 60,
        AppointmentKind $kind = AppointmentKind::Visit,
        AppointmentStatus $status = AppointmentStatus::Scheduled,
        ?string $dealId = null,
        ?\DateTimeImmutable $confirmedAt = null,
        ?\DateTimeImmutable $shownAt = null,
        ?string $note = null,
        ?\DateTimeImmutable $reminderSentAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->customerId = $customerId;
        $this->userId = $userId;
        $this->scheduledAt = $scheduledAt;
        $this->durationMinutes = $durationMinutes;
        $this->kind = $kind;
        $this->status = $status;
        $this->dealId = $dealId;
        $this->confirmedAt = $confirmedAt;
        $this->shownAt = $shownAt;
        $this->note = $note;
        $this->reminderSentAt = $reminderSentAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function scheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function durationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function kind(): AppointmentKind
    {
        return $this->kind;
    }

    public function status(): AppointmentStatus
    {
        return $this->status;
    }

    public function dealId(): ?string
    {
        return $this->dealId;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function shownAt(): ?\DateTimeImmutable
    {
        return $this->shownAt;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function reminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->scheduledAt->modify(\sprintf('+%d minutes', $this->durationMinutes));
    }

    /**
     * Still to happen: scheduled or confirmed. Shown, no-show and cancelled appointments are history.
     */
    public function isOpen(): bool
    {
        return AppointmentStatus::Scheduled === $this->status || AppointmentStatus::Confirmed === $this->status;
    }

    /**
     * Whether it takes the rep's time: everything but cancelled and no-show appointments.
     */
    public function occupiesRep(): bool
    {
        return AppointmentStatus::Cancelled !== $this->status && AppointmentStatus::NoShow !== $this->status;
    }

    /**
     * @throws AppointmentNotOpen once it was shown, missed or cancelled
     */
    public function confirm(\DateTimeImmutable $now): void
    {
        $this->mustBeOpen();
        $this->status = AppointmentStatus::Confirmed;
        $this->confirmedAt ??= $now;
    }

    /**
     * @throws AppointmentNotOpen
     */
    public function markShown(\DateTimeImmutable $now): void
    {
        $this->mustBeOpen();
        $this->status = AppointmentStatus::Shown;
        $this->shownAt = $now;
    }

    /**
     * @throws AppointmentNotOpen
     */
    public function markNoShow(): void
    {
        $this->mustBeOpen();
        $this->status = AppointmentStatus::NoShow;
    }

    /**
     * @throws AppointmentNotOpen
     */
    public function cancel(): void
    {
        $this->mustBeOpen();
        $this->status = AppointmentStatus::Cancelled;
    }

    /**
     * Moves it in time: the customer has to confirm again and the rep is reminded again.
     *
     * @throws AppointmentNotOpen
     */
    public function moveTo(\DateTimeImmutable $scheduledAt): void
    {
        $this->mustBeOpen();
        $this->scheduledAt = $scheduledAt;
        $this->status = AppointmentStatus::Scheduled;
        $this->confirmedAt = null;
        $this->reminderSentAt = null;
    }

    /**
     * @throws AppointmentNotOpen
     */
    public function reassignTo(string $userId): void
    {
        $this->mustBeOpen();
        $this->userId = $userId;
    }

    /**
     * Length, kind and note of an appointment that has not happened yet.
     *
     * @throws AppointmentNotOpen
     */
    public function describe(?int $durationMinutes, ?AppointmentKind $kind, ?string $note): void
    {
        $this->mustBeOpen();
        $this->durationMinutes = $durationMinutes ?? $this->durationMinutes;
        $this->kind = $kind ?? $this->kind;
        $this->note = null === $note ? $this->note : ('' === trim($note) ? null : trim($note));
    }

    /**
     * The reminder to the rep went out; it is sent once per time slot.
     */
    public function markReminderSent(\DateTimeImmutable $now): void
    {
        $this->reminderSentAt = $now;
    }

    /**
     * Whether a reminder queued for $queuedFor should still go out: the slot did not move, the appointment is still on
     * and nobody was reminded yet.
     */
    public function needsReminderFor(\DateTimeImmutable $queuedFor): bool
    {
        return $this->isOpen() && null === $this->reminderSentAt && $this->scheduledAt == $queuedFor;
    }

    /**
     * @throws AppointmentNotOpen
     */
    public function ensureOpen(): void
    {
        $this->mustBeOpen();
    }

    private function mustBeOpen(): void
    {
        if (!$this->isOpen()) {
            throw AppointmentNotOpen::in($this->status);
        }
    }
}
