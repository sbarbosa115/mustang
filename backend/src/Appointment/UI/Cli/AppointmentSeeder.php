<?php

namespace App\Appointment\UI\Cli;

use App\Appointment\Domain\Model\Appointment;
use App\Appointment\Domain\Model\AppointmentKind;
use App\Appointment\Domain\Model\AppointmentStatus;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Appointments around the time of seeding, on the hour: one later today (not confirmed), a few on the next days,
 * one shown yesterday, one missed, one cancelled, and one at Other Motors. They belong to the customers of
 * DealSeeder's deals and to the rep of that deal. Customers 11-20 and 25-30 have none, so tests can book for them.
 * Keys: appointment.<customer n> and appointment.other.1.
 */
final readonly class AppointmentSeeder implements DemoSeeder
{
    /**
     * customer n => [hours from now, status, kind, minutes].
     */
    private const array APPOINTMENTS = [
        22 => [2, AppointmentStatus::Scheduled, AppointmentKind::Visit, 60],
        3 => [26, AppointmentStatus::Confirmed, AppointmentKind::Visit, 60],
        2 => [28, AppointmentStatus::Cancelled, AppointmentKind::Visit, 60],
        24 => [50, AppointmentStatus::Scheduled, AppointmentKind::TestDrive, 45],
        1 => [74, AppointmentStatus::Scheduled, AppointmentKind::Visit, 60],
        4 => [-22, AppointmentStatus::Shown, AppointmentKind::Visit, 60],
        9 => [-30, AppointmentStatus::NoShow, AppointmentKind::Visit, 60],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 500;
    }

    public function seed(SeedContext $context): void
    {
        $dealership = $context->id('dealership.mustang');
        $reps = [$context->id('user.sales@mustang.test'), $context->id('user.sales2@mustang.test')];
        foreach (self::APPOINTMENTS as $n => [$hours, $status, $kind, $minutes]) {
            $appointment = $this->appointment($context, $dealership, $context->id('customer.'.$n), $reps[$n % 2], $hours, $status, $kind, $minutes, $context->has('deal.'.$n) ? $context->id('deal.'.$n) : null);
            $context->set('appointment.'.$n, $appointment->id());
        }
        $other = $this->appointment($context, $context->id('dealership.other'), $context->id('customer.other.1'), $context->id('user.other@mustang.test'), 27, AppointmentStatus::Scheduled, AppointmentKind::Visit, 60, null);
        $context->set('appointment.other.1', $other->id());
    }

    private function appointment(SeedContext $context, string $dealership, string $customer, string $rep, int $hours, AppointmentStatus $status, AppointmentKind $kind, int $minutes, ?string $deal): Appointment
    {
        $hour = $context->now->setTime((int) $context->now->format('G'), 0);
        $at = $hour->modify(\sprintf('%+d hours', $hours));
        $appointment = new Appointment(
            $dealership,
            $customer,
            $rep,
            $at,
            $minutes,
            $kind,
            $status,
            $deal,
            confirmedAt: AppointmentStatus::Scheduled === $status || AppointmentStatus::Cancelled === $status ? null : $at->modify('-1 day'),
            shownAt: AppointmentStatus::Shown === $status ? $at->modify('+5 minutes') : null,
        );
        $this->em->persist($appointment);

        return $appointment;
    }
}
