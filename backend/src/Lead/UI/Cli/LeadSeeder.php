<?php

namespace App\Lead\UI\Cli;

use App\Lead\Domain\Model\Lead;
use App\Lead\Domain\Model\LeadSource;
use App\Lead\Domain\Model\LeadStatus;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ten leads for Mustang Motors on seeded customers (new, assigned, contacted and closed) and two for Other Motors.
 * Written directly, so no LeadCaptured is raised: the Deal context seeds its own deals. Keys: lead.<n>, lead.other.<n>.
 */
final readonly class LeadSeeder implements DemoSeeder
{
    /**
     * customer number, source, rep (sales, sales2 or none), status, minutes ago, vehicle of interest, channel note.
     */
    private const array LEADS = [
        [1, LeadSource::Website, 'sales2', LeadStatus::Contacted, 4320, '2025 Toyota Highlander', 'Website form'],
        [6, LeadSource::Phone, 'sales', LeadStatus::Assigned, 95, '2024 Honda CR-V EX', 'Called the main line'],
        [7, LeadSource::WalkIn, 'sales2', LeadStatus::Assigned, 240, 'Used F-150 under 40k', null],
        [8, LeadSource::Website, null, LeadStatus::New, 25, '2025 RAV4 Hybrid', 'Website form'],
        [9, LeadSource::Manual, 'sales', LeadStatus::Contacted, 2880, null, 'Referral from Sarah Johnson'],
        [11, LeadSource::Phone, null, LeadStatus::New, 140, 'Tacoma TRD Off-Road', 'Missed call, voicemail left'],
        [12, LeadSource::Website, 'sales', LeadStatus::Closed, 10080, '2023 Camry SE', 'Website form'],
        [13, LeadSource::WalkIn, 'sales', LeadStatus::Assigned, 30, 'Three-row SUV', 'Saturday walk-in'],
        [14, LeadSource::Other, 'sales2', LeadStatus::Assigned, 1500, null, 'Met at the car show'],
        [16, LeadSource::Phone, 'sales2', LeadStatus::Contacted, 5760, 'Certified Civic', null],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 800;
    }

    public function seed(SeedContext $context): void
    {
        $dealership = $context->id('dealership.mustang');
        $reps = ['sales' => $context->id('user.sales@mustang.test'), 'sales2' => $context->id('user.sales2@mustang.test')];
        foreach (self::LEADS as $n => [$customer, $source, $rep, $status, $minutesAgo, $vehicle, $note]) {
            $receivedAt = $context->now->modify(\sprintf('-%d minutes', $minutesAgo));
            $lead = new Lead(
                $dealership,
                $context->id('customer.'.$customer),
                $source,
                $receivedAt,
                $status,
                $note,
                null === $rep ? null : $reps[$rep],
                $vehicle,
                LeadStatus::Contacted === $status || LeadStatus::Closed === $status ? $receivedAt->modify('+12 minutes') : null,
            );
            $this->em->persist($lead);
            $context->set('lead.'.($n + 1), $lead->id());
        }

        $other = $context->id('dealership.other');
        $otherAdmin = $context->id('user.other@mustang.test');
        foreach ([1, 2] as $n) {
            $lead = new Lead($other, $context->id('customer.other.'.$n), LeadSource::Website, $context->now->modify(\sprintf('-%d hours', $n * 3)), LeadStatus::Assigned, 'Website form', $otherAdmin);
            $this->em->persist($lead);
            $context->set('lead.other.'.$n, $lead->id());
        }
    }
}
