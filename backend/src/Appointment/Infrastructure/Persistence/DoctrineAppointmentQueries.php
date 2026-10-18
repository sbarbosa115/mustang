<?php

namespace App\Appointment\Infrastructure\Persistence;

use App\Appointment\Application\Query\AppointmentFilter;
use App\Appointment\Application\Query\AppointmentQueries;
use App\Appointment\Domain\Model\Appointment;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAppointmentQueries implements AppointmentQueries
{
    public function __construct(private EntityManagerInterface $em, private TenantContext $tenant)
    {
    }

    public function list(AppointmentFilter $filter): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.dealershipId = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId())
            ->orderBy('a.scheduledAt', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->setMaxResults(self::LIMIT);
        if (null !== $filter->from) {
            $qb->andWhere('a.scheduledAt >= :from')->setParameter('from', $filter->from);
        }
        if (null !== $filter->to) {
            $qb->andWhere('a.scheduledAt < :to')->setParameter('to', $filter->to);
        }
        if (null !== $filter->userId) {
            $qb->andWhere('a.userId = :user')->setParameter('user', $filter->userId);
        }
        if (null !== $filter->customerId) {
            $qb->andWhere('a.customerId = :customer')->setParameter('customer', $filter->customerId);
        }

        /* @var list<Appointment> */
        return $qb->getQuery()->getResult();
    }

    public function byId(string $id): Appointment
    {
        $appointment = Id::isValid($id)
            ? $this->em->getRepository(Appointment::class)->findOneBy(['id' => $id, 'dealershipId' => $this->tenant->dealershipId()])
            : null;

        return $appointment ?? throw new NotFound('appointment_not_found', 'The record does not exist.', ['id' => $id]);
    }
}
