<?php

namespace App\Worklist\Infrastructure\Persistence;

use App\Shared\Application\Port\ConsentStates;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Channel;
use App\Worklist\Application\Query\WorklistQueries;
use App\Worklist\Domain\Model\AppointmentFacts;
use App\Worklist\Domain\Model\ConversationFacts;
use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\DealFacts;
use App\Worklist\Domain\Model\LeadFacts;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Rule\StageThresholds;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Plain SQL over the other contexts' tables, one query per kind of fact, every one filtered by the dealership. The
 * facts are joined per customer here; the rules (Domain/Rule) decide what they mean.
 */
final readonly class DoctrineWorklistQueries implements WorklistQueries
{
    private const string FAR_PAST = '1000-01-01 00:00:00';
    /** A message that reached the customer, or is on its way: blocked and failed attempts do not count as contact. */
    private const string DELIVERED = "mo.direction = 'out' AND mo.status IN ('queued', 'sent')";

    public function __construct(
        private Connection $connection,
        private TenantContext $tenant,
        private ConsentStates $consent,
    ) {
    }

    public function timezone(): string
    {
        $timezone = $this->connection->fetchOne('SELECT timezone FROM dealerships WHERE id = ?', [$this->tenant->dealershipId()]);

        return \is_string($timezone) && '' !== $timezone ? $timezone : 'America/New_York';
    }

    public function customerFacts(Now $now, ?string $ownerUserId = null): array
    {
        $dealership = $this->tenant->dealershipId();
        $conversations = $this->conversations($dealership);
        $leads = $this->leads($dealership);
        $today = $this->appointmentsToday($dealership, $now);
        $noShows = $this->noShows($dealership, $now);
        $deals = $this->openDeals($dealership);

        $candidates = array_keys(
            array_filter($conversations, static fn (ConversationFacts $c): bool => $c->unanswered())
            + $leads + $today + $noShows
            + array_filter($deals, static fn (DealFacts $d): bool => null !== StageThresholds::days($d->stage)),
        );
        $customers = $this->customers($dealership, array_map(strval(...), $candidates));

        $facts = [];
        foreach ($customers as $id => $row) {
            $deal = $deals[$id] ?? null;
            $owner = $row['sales1_user_id'] ?? $deal->assignedUserId ?? $row['bdc_user_id'];
            if (null !== $ownerUserId && $owner !== $ownerUserId) {
                continue;
            }
            $facts[$id] = [$row, $owner, $deal];
        }
        $consent = $this->consent->forCustomers(array_map(strval(...), array_keys($facts)));

        $result = [];
        foreach ($facts as $id => [$row, $owner, $deal]) {
            $id = (string) $id;
            $result[] = new CustomerFacts(
                customerId: $id,
                customerName: trim($row['first_name'].' '.$row['last_name']),
                consent: $consent[$id] ?? ['text' => 'missing', 'email' => 'missing', 'call' => 'missing'],
                ownerUserId: $owner,
                phone: $row['phone'],
                conversation: $conversations[$id] ?? null,
                lead: $leads[$id] ?? null,
                appointmentToday: $today[$id] ?? null,
                lastNoShow: $noShows[$id] ?? null,
                deal: $deal,
            );
        }

        return $result;
    }

    /**
     * Every conversation the customer wrote in (the last inbound channel is the suggested one even once answered).
     *
     * @return array<string, ConversationFacts>
     */
    private function conversations(string $dealership): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT conv.customer_id, conv.last_inbound_at, conv.last_outbound_at,
                (SELECT mi.channel FROM messages mi
                  WHERE mi.dealership_id = conv.dealership_id AND mi.conversation_id = conv.id AND mi.direction = 'in'
                  ORDER BY mi.created_at DESC, mi.id DESC LIMIT 1) AS last_inbound_channel,
                EXISTS (SELECT 1 FROM messages mq
                  WHERE mq.dealership_id = conv.dealership_id AND mq.conversation_id = conv.id AND mq.direction = 'in'
                    AND mq.created_at > COALESCE(conv.last_outbound_at, :far_past) AND mq.body LIKE '%?%') AS question
             FROM conversations conv
             WHERE conv.dealership_id = :dealership AND conv.last_inbound_at IS NOT NULL",
            ['dealership' => $dealership, 'far_past' => self::FAR_PAST],
        );
        $facts = [];
        foreach ($rows as $row) {
            $facts[(string) $row['customer_id']] = new ConversationFacts(
                self::date($row['last_inbound_at']),
                self::date($row['last_outbound_at']),
                Channel::tryFrom((string) $row['last_inbound_channel']),
                (bool) $row['question'],
            );
        }

        return $facts;
    }

    /**
     * The customer's latest open lead (new or assigned; a contacted or closed lead needs nobody). It counts as
     * contacted once marked so, or once a message went out to the customer after it came in.
     *
     * @return array<string, LeadFacts>
     */
    private function leads(string $dealership): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf("SELECT l.id, l.customer_id, l.received_at, l.source, l.assigned_user_id, l.vehicle_interest_text,
                    l.first_contacted_at IS NOT NULL OR EXISTS (SELECT 1 FROM messages mo
                      JOIN conversations c ON c.id = mo.conversation_id AND c.dealership_id = mo.dealership_id
                      WHERE mo.dealership_id = l.dealership_id AND c.customer_id = l.customer_id AND %s
                        AND mo.created_at >= l.received_at) AS contacted
                 FROM leads l
                 WHERE l.dealership_id = :dealership AND l.status IN ('new', 'assigned')
                 ORDER BY l.received_at DESC, l.id DESC", self::DELIVERED),
            ['dealership' => $dealership],
        );
        $facts = [];
        foreach ($rows as $row) {
            $facts[(string) $row['customer_id']] ??= new LeadFacts(
                (string) $row['id'],
                self::date($row['received_at']) ?? new \DateTimeImmutable(),
                (string) $row['source'],
                self::nullableString($row['assigned_user_id']),
                (bool) $row['contacted'],
                self::nullableString($row['vehicle_interest_text']),
            );
        }

        return $facts;
    }

    /**
     * Each customer's appointment today in the dealership's day: an unconfirmed one first, else the first confirmed.
     *
     * @return array<string, AppointmentFacts>
     */
    private function appointmentsToday(string $dealership, Now $now): array
    {
        [$start, $end] = $now->today();
        $rows = $this->connection->fetchAllAssociative(
            "SELECT a.id, a.customer_id, a.scheduled_at, a.status, a.kind FROM appointments a
             WHERE a.dealership_id = :dealership AND a.status IN ('scheduled', 'confirmed')
               AND a.scheduled_at >= :start AND a.scheduled_at < :end
             ORDER BY a.status = 'scheduled' DESC, a.scheduled_at, a.id",
            ['dealership' => $dealership, 'start' => self::sql($start), 'end' => self::sql($end)],
        );
        $facts = [];
        foreach ($rows as $row) {
            $facts[(string) $row['customer_id']] ??= self::appointment($row);
        }

        return $facts;
    }

    /**
     * Each customer's latest no-show, and whether someone followed up: a message out, or another appointment booked
     * (and not cancelled) after it.
     *
     * @return array<string, AppointmentFacts>
     */
    private function noShows(string $dealership, Now $now): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf("SELECT a.id, a.customer_id, a.scheduled_at, a.status, a.kind,
                    EXISTS (SELECT 1 FROM messages mo
                      JOIN conversations c ON c.id = mo.conversation_id AND c.dealership_id = mo.dealership_id
                      WHERE mo.dealership_id = a.dealership_id AND c.customer_id = a.customer_id AND %s
                        AND mo.created_at > a.scheduled_at)
                    OR EXISTS (SELECT 1 FROM appointments b
                      WHERE b.dealership_id = a.dealership_id AND b.customer_id = a.customer_id AND b.id <> a.id
                        AND b.status <> 'cancelled' AND b.created_at > a.scheduled_at) AS followed_up
                 FROM appointments a
                 WHERE a.dealership_id = :dealership AND a.status = 'no_show' AND a.scheduled_at <= :now
                 ORDER BY a.scheduled_at DESC, a.id DESC", self::DELIVERED),
            ['dealership' => $dealership, 'now' => self::sql($now->at)],
        );
        $facts = [];
        foreach ($rows as $row) {
            $facts[(string) $row['customer_id']] ??= self::appointment($row, (bool) $row['followed_up']);
        }

        return $facts;
    }

    /**
     * Each customer's open deal (the newest, should there be more than one).
     *
     * @return array<string, DealFacts>
     */
    private function openDeals(string $dealership): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT d.id, d.customer_id, d.stage, d.stage_entered_at, d.last_activity_at, d.assigned_user_id,
                    d.expected_gross_cents,
                    COALESCE(d.vehicle_text, CONCAT_WS(' ', v.year, v.make, v.model)) AS vehicle
             FROM deals d
             LEFT JOIN vehicles v ON v.id = d.vehicle_id AND v.dealership_id = d.dealership_id
             WHERE d.dealership_id = :dealership AND d.stage NOT IN ('sold', 'delivered', 'lost')
             ORDER BY d.created_at DESC, d.id DESC",
            ['dealership' => $dealership],
        );
        $facts = [];
        foreach ($rows as $row) {
            $entered = self::date($row['stage_entered_at']) ?? new \DateTimeImmutable();
            $activity = self::date($row['last_activity_at']);
            $facts[(string) $row['customer_id']] ??= new DealFacts(
                (string) $row['id'],
                (string) $row['stage'],
                $entered,
                null !== $activity && $activity > $entered ? $activity : $entered,
                self::nullableString($row['assigned_user_id']),
                self::nullableString($row['vehicle']),
                null === $row['expected_gross_cents'] ? null : (int) $row['expected_gross_cents'],
            );
        }

        return $facts;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{first_name: string, last_name: string, sales1_user_id: ?string, bdc_user_id: ?string, phone: ?string}>
     */
    private function customers(string $dealership, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT cu.id, cu.first_name, cu.last_name, cu.sales1_user_id, cu.bdc_user_id,
                (SELECT cc.value FROM customer_contacts cc
                  WHERE cc.dealership_id = cu.dealership_id AND cc.customer_id = cu.id AND cc.kind = 'phone'
                  ORDER BY cc.is_primary DESC, cc.created_at, cc.id LIMIT 1) AS phone
             FROM customers cu
             WHERE cu.dealership_id = :dealership AND cu.id IN (:ids) AND cu.archived_at IS NULL
             ORDER BY cu.last_name, cu.first_name, cu.id",
            ['dealership' => $dealership, 'ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );
        $customers = [];
        foreach ($rows as $row) {
            $customers[(string) $row['id']] = [
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'sales1_user_id' => self::nullableString($row['sales1_user_id']),
                'bdc_user_id' => self::nullableString($row['bdc_user_id']),
                'phone' => self::nullableString($row['phone']),
            ];
        }

        return $customers;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function appointment(array $row, bool $followedUp = false): AppointmentFacts
    {
        return new AppointmentFacts(
            (string) $row['id'],
            self::date($row['scheduled_at']) ?? new \DateTimeImmutable(),
            (string) $row['status'],
            (string) $row['kind'],
            $followedUp,
        );
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        return \is_string($value) && '' !== $value ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value || '' === $value ? null : (string) $value;
    }

    private static function sql(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
