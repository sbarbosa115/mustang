<?php

namespace App\Appointment\Infrastructure\Persistence;

use App\Appointment\Domain\Model\Appointment;
use App\Appointment\Domain\Model\AppointmentStatus;
use App\Appointment\Domain\Repository\AppointmentRepository;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Appointment>
 */
final class DoctrineAppointmentRepository extends TenantScopedRepository implements AppointmentRepository
{
    /** The longest an appointment can last (the Input allows 480 minutes), to bound the candidates of an overlap check. */
    private const int LONGEST_MINUTES = 480;

    protected function entityClass(): string
    {
        return Appointment::class;
    }

    protected function notFoundCode(): string
    {
        return 'appointment_not_found';
    }

    public function get(string $id): Appointment
    {
        return $this->getScoped($id);
    }

    public function getInDealership(string $id, string $dealershipId): Appointment
    {
        $appointment = Id::isValid($id) ? $this->em->getRepository(Appointment::class)->findOneBy(['id' => $id, 'dealershipId' => $dealershipId]) : null;

        return $appointment ?? throw new NotFound($this->notFoundCode(), 'The record does not exist.', ['id' => $id]);
    }

    public function add(Appointment $appointment): void
    {
        $this->persist($appointment);
    }

    public function overlapping(string $dealershipId, string $userId, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $exceptId = null): array
    {
        /** @var list<Appointment> $candidates */
        $candidates = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.dealershipId = :dealership')
            ->andWhere('a.userId = :user')
            ->andWhere('a.scheduledAt < :to')
            ->andWhere('a.scheduledAt > :earliest')
            ->andWhere('a.status NOT IN (:free)')
            ->setParameter('dealership', $dealershipId)
            ->setParameter('user', $userId)
            ->setParameter('to', $to)
            ->setParameter('earliest', $from->modify(\sprintf('-%d minutes', self::LONGEST_MINUTES)))
            ->setParameter('free', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->getQuery()
            ->getResult();

        // Two slots overlap when each starts before the other ends; back to back is fine.
        return array_values(array_filter(
            $candidates,
            static fn (Appointment $a): bool => $a->id() !== $exceptId && $a->endsAt() > $from,
        ));
    }
}
