<?php

namespace App\Reporting\Infrastructure\Persistence;

use App\Reporting\Application\Query\KpiQueries;
use App\Reporting\Application\Query\KpiSnapshot;
use Doctrine\DBAL\Connection;

/**
 * Plain SQL over leads, conversations, messages, appointments and deals, always inside one dealership. Every datetime
 * column is UTC; the period arrives as UTC instants already drawn from the dealership's local midnights.
 */
final readonly class DoctrineKpiQueries implements KpiQueries
{
    private const string DEFAULT_TIMEZONE = 'America/New_York';
    private const string FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    public function timezone(string $dealershipId): string
    {
        $zone = $this->connection->fetchOne('SELECT timezone FROM dealerships WHERE id = :id', ['id' => $dealershipId]);

        return \is_string($zone) && '' !== $zone ? $zone : self::DEFAULT_TIMEZONE;
    }

    public function snapshot(string $dealershipId, \DateTimeImmutable $start, \DateTimeImmutable $end): KpiSnapshot
    {
        $params = ['dealership' => $dealershipId, 'start' => $start->format(self::FORMAT), 'end' => $end->format(self::FORMAT)];
        $funnel = $this->funnel($params);

        return new KpiSnapshot(
            $funnel['leads'],
            $funnel['contacted'],
            $funnel['appointments'],
            $funnel['shows'],
            $funnel['sales'],
            $funnel['followed'],
            $this->firstResponseMinutes($params),
            $this->unitsPerSalesperson($params),
        );
    }

    /**
     * One row per lead received in the period with a flag per step, then summed. A step counts only when the one
     * before it did.
     *
     * @param array<string, string> $params
     *
     * @return array{leads: int, followed: int, contacted: int, appointments: int, shows: int, sales: int}
     */
    private function funnel(array $params): array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
            SELECT COUNT(*) AS leads,
                   COALESCE(SUM(t.followed), 0) AS followed,
                   COALESCE(SUM(t.contacted), 0) AS contacted,
                   COALESCE(SUM(t.contacted AND t.booked), 0) AS appointments,
                   COALESCE(SUM(t.contacted AND t.booked AND t.shown), 0) AS shows,
                   COALESCE(SUM(t.contacted AND t.booked AND t.shown AND t.sold), 0) AS sales
              FROM (
                SELECT
                  EXISTS (
                    SELECT 1 FROM messages o JOIN conversations c ON c.id = o.conversation_id
                     WHERE c.dealership_id = l.dealership_id AND c.customer_id = l.customer_id
                       AND o.direction = 'out' AND o.status = 'sent'
                       AND o.sent_at >= l.received_at AND o.sent_at <= DATE_ADD(l.received_at, INTERVAL 24 HOUR)
                  ) AS followed,
                  EXISTS (
                    SELECT 1 FROM messages o JOIN conversations c ON c.id = o.conversation_id
                     WHERE c.dealership_id = l.dealership_id AND c.customer_id = l.customer_id
                       AND o.direction = 'out' AND o.status = 'sent' AND o.sent_at >= l.received_at
                       AND EXISTS (
                         SELECT 1 FROM messages i
                          WHERE i.conversation_id = o.conversation_id AND i.direction = 'in' AND i.received_at > o.sent_at
                       )
                  ) AS contacted,
                  EXISTS (
                    SELECT 1 FROM appointments a
                     WHERE a.dealership_id = l.dealership_id AND a.customer_id = l.customer_id
                       AND a.status <> 'cancelled' AND a.created_at >= l.received_at
                  ) AS booked,
                  EXISTS (
                    SELECT 1 FROM appointments a
                     WHERE a.dealership_id = l.dealership_id AND a.customer_id = l.customer_id
                       AND a.status = 'shown' AND a.created_at >= l.received_at
                  ) AS shown,
                  EXISTS (
                    SELECT 1 FROM deals d
                     WHERE d.dealership_id = l.dealership_id AND d.customer_id = l.customer_id
                       AND (d.lead_id = l.id OR d.lead_id IS NULL)
                       AND COALESCE(d.sold_at, d.delivered_at) IS NOT NULL
                       AND COALESCE(d.sold_at, d.delivered_at) >= l.received_at
                  ) AS sold
                  FROM leads l
                 WHERE l.dealership_id = :dealership AND l.received_at >= :start AND l.received_at < :end
              ) t
            SQL, $params) ?: [];

        return [
            'leads' => (int) ($row['leads'] ?? 0),
            'followed' => (int) ($row['followed'] ?? 0),
            'contacted' => (int) ($row['contacted'] ?? 0),
            'appointments' => (int) ($row['appointments'] ?? 0),
            'shows' => (int) ($row['shows'] ?? 0),
            'sales' => (int) ($row['sales'] ?? 0),
        ];
    }

    /**
     * Median minutes from "the customer is waiting" to the next text or email sent. Waiting starts when a lead is
     * received, and when a customer writes after the last reply (further messages before the reply do not start
     * another wait). Waits nobody answered yet are left out.
     *
     * @param array<string, string> $params
     */
    private function firstResponseMinutes(array $params): ?float
    {
        $seconds = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT w.seconds FROM (
              SELECT TIMESTAMPDIFF(SECOND, l.received_at, (
                       SELECT MIN(o.sent_at) FROM messages o JOIN conversations c ON c.id = o.conversation_id
                        WHERE c.dealership_id = l.dealership_id AND c.customer_id = l.customer_id
                          AND o.direction = 'out' AND o.status = 'sent' AND o.sent_at >= l.received_at
                     )) AS seconds
                FROM leads l
               WHERE l.dealership_id = :dealership AND l.received_at >= :start AND l.received_at < :end
              UNION ALL
              SELECT TIMESTAMPDIFF(SECOND, i.received_at, (
                       SELECT MIN(o.sent_at) FROM messages o
                        WHERE o.conversation_id = i.conversation_id
                          AND o.direction = 'out' AND o.status = 'sent' AND o.sent_at > i.received_at
                     )) AS seconds
                FROM messages i
               WHERE i.dealership_id = :dealership AND i.direction = 'in'
                 AND i.received_at >= :start AND i.received_at < :end
                 AND NOT EXISTS (
                   SELECT 1 FROM messages p
                    WHERE p.conversation_id = i.conversation_id AND p.direction = 'in' AND p.received_at < i.received_at
                      AND p.received_at > COALESCE((
                        SELECT MAX(o2.sent_at) FROM messages o2
                         WHERE o2.conversation_id = i.conversation_id AND o2.direction = 'out'
                           AND o2.status = 'sent' AND o2.sent_at < i.received_at
                      ), '1970-01-01 00:00:00')
                 )
            ) w
            WHERE w.seconds IS NOT NULL
            ORDER BY w.seconds
            SQL, $params);
        $count = \count($seconds);
        if (0 === $count) {
            return null;
        }
        $middle = intdiv($count, 2);
        $median = 1 === $count % 2 ? (float) $seconds[$middle] : ((float) $seconds[$middle - 1] + (float) $seconds[$middle]) / 2;

        return round($median / 60, 1);
    }

    /**
     * Deals delivered in the period divided by the salespeople who could have delivered them: the active users with
     * the sales role, plus anyone else a delivered deal is assigned to.
     *
     * @param array<string, string> $params
     */
    private function unitsPerSalesperson(array $params): ?float
    {
        $units = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM deals WHERE dealership_id = :dealership AND delivered_at >= :start AND delivered_at < :end',
            $params,
        );
        $people = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*) FROM (
              SELECT id AS person FROM users WHERE dealership_id = :dealership AND active = 1 AND role = 'sales'
              UNION
              SELECT assigned_user_id FROM deals
               WHERE dealership_id = :dealership AND delivered_at >= :start AND delivered_at < :end AND assigned_user_id IS NOT NULL
            ) p
            SQL, $params);

        return 0 === $people ? null : round($units / $people, 1);
    }
}
