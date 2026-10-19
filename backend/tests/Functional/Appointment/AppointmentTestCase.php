<?php

namespace App\Tests\Functional\Appointment;

use App\Tests\Support\ApiTestCase;

/**
 * Helpers for the appointment tests. Times are typed the way the UI sends them: local to the dealership
 * (America/New_York), "yyyy-MM-ddTHH:mm".
 */
abstract class AppointmentTestCase extends ApiTestCase
{
    protected const string ZONE = 'America/New_York';

    /**
     * A local time n days from now at the given clock time, e.g. local(3, '10:00'). Future days start two weeks out,
     * clear of the appointments AppointmentSeeder puts in the next few days; negative days are in the past.
     */
    protected function local(int $days, string $time = '10:00'): string
    {
        $days += $days > 0 ? 14 : 0;

        return new \DateTimeImmutable('now', new \DateTimeZone(self::ZONE))->modify(\sprintf('%+d days', $days))->format('Y-m-d').'T'.$time;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    protected function book(int $customer, string $scheduledAt, array $extra = []): array
    {
        return $this->request('POST', '/api/v1/appointments', ['customer_id' => $this->customerId($customer), 'scheduled_at' => $scheduledAt] + $extra);
    }

    protected function utc(string $iso): string
    {
        return new \DateTimeImmutable($iso)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i');
    }

    protected function stageOf(int $customer): ?string
    {
        $stage = $this->db()->fetchOne("SELECT stage FROM deals WHERE customer_id = ? AND stage NOT IN ('delivered','lost')", [$this->customerId($customer)]);

        return false === $stage ? null : (string) $stage;
    }
}
