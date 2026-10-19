<?php

namespace App\Timeline\UI\Cli;

use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use App\Timeline\Domain\Model\Activity;
use App\Timeline\Domain\Model\ActivityKind;
use App\Timeline\Domain\Repository\ActivityLog;

/**
 * The other seeders write rows without publishing events, so these customers would have an empty timeline: give a
 * few of them a believable history (a lead, a text exchange, a stage move, an appointment, a note) over the days
 * before seeding. Customers 1-3 and 21-24; the rest start empty. Safe to run twice.
 */
final readonly class TimelineSeeder implements DemoSeeder
{
    /**
     * [hours before now, kind, code, summary, ref type, who: null or a user key].
     */
    private const array HISTORY = [
        [120, ActivityKind::Lead, 'lead_captured', 'Lead from website, interested in white F-150', 'lead', null],
        [119, ActivityKind::MessageOut, 'message_sent_text', 'Text message sent', 'message', 'sales@mustang.test'],
        [96, ActivityKind::MessageIn, 'message_received_text', 'Text message received', 'message', null],
        [95, ActivityKind::Stage, 'stage_changed_engaged', 'Deal moved from new to engaged', 'deal', 'sales@mustang.test'],
        [72, ActivityKind::Consent, 'consent_recorded_text', 'Consent recorded for text (web_form)', 'consent', 'sales@mustang.test'],
        [48, ActivityKind::Appointment, 'appointment_booked', 'Appointment booked', 'appointment', 'sales@mustang.test'],
        [30, ActivityKind::Note, 'note_added', 'Note added', 'note', 'sales@mustang.test'],
    ];

    private const array CUSTOMERS = [1, 2, 3, 21, 22, 23, 24];

    public function __construct(private ActivityLog $log)
    {
    }

    public static function priority(): int
    {
        return 10;
    }

    public function seed(SeedContext $context): void
    {
        $dealership = $context->id('dealership.mustang');
        foreach (self::CUSTOMERS as $n) {
            $customer = $context->id('customer.'.$n);
            foreach (self::HISTORY as $i => [$hours, $kind, $code, $summary, $refType, $who]) {
                $key = $code.':'.substr(hash('sha1', 'seed|'.$customer.'|'.$i), 0, 32);
                if ($this->log->has($key)) {
                    continue;
                }
                $this->log->append(new Activity(
                    $dealership,
                    $customer,
                    $kind,
                    $context->now->modify(\sprintf('-%d hours', $hours + $n)),
                    $summary,
                    $refType,
                    null,
                    null === $who ? null : $context->id('user.'.$who),
                    $key,
                ));
            }
        }
    }
}
