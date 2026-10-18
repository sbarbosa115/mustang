<?php

namespace App\Deal\UI\Cli;

use App\Deal\Domain\Model\Deal;
use App\Deal\Domain\Model\DealStage;
use App\Deal\Domain\Model\TradeIn;
use App\Shared\UI\Cli\DemoSeeder;
use App\Shared\UI\Cli\SeedContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * One deal in every stage for Mustang Motors (customers 1-10), deals for the customers with seeded conversations
 * (21-24), and one for Other Motors. Customers 11-20 and 25-30 have no deal, so tests can open one for them.
 * Each deal walks the stages in order, a day apart, ending in its stage the given number of days ago.
 * Keys: deal.<customer n> and deal.other.1.
 */
final readonly class DealSeeder implements DemoSeeder
{
    /**
     * customer n => [stage, days in that stage, vehicle stock number or null, vehicle text or null, expected gross in dollars or null].
     */
    private const array DEALS = [
        1 => [DealStage::New, 0, 'MM-1001', null, null],
        2 => [DealStage::Engaged, 2, null, 'Three-row SUV around $45k', null],
        3 => [DealStage::Appointment, 1, 'MM-1005', null, 2800],
        4 => [DealStage::Visit, 3, 'MM-1007', null, 3100],
        5 => [DealStage::Proposal, 2, 'MM-1003', null, 4200],
        6 => [DealStage::Sold, 1, 'MM-1002', null, 3600],
        7 => [DealStage::Delivered, 10, 'MM-1014', null, 2900],
        8 => [DealStage::Lost, 5, null, 'Used truck under $30k', null],
        9 => [DealStage::Engaged, 5, 'MM-1010', null, null],
        10 => [DealStage::New, 0, null, null, null],
        21 => [DealStage::Engaged, 0, null, '2024 Tacoma TRD', null],
        22 => [DealStage::Appointment, 0, 'MM-1007', null, null],
        23 => [DealStage::Engaged, 1, null, null, null],
        24 => [DealStage::Proposal, 2, 'MM-1004', null, 2500],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public static function priority(): int
    {
        return 600;
    }

    public function seed(SeedContext $context): void
    {
        $dealership = $context->id('dealership.mustang');
        $reps = [$context->id('user.sales@mustang.test'), $context->id('user.sales2@mustang.test')];
        foreach (self::DEALS as $n => [$stage, $days, $stock, $text, $gross]) {
            // The assignee is the customer's Sales 1 (CustomerSeeder alternates the two reps the same way).
            $deal = $this->walk($dealership, $context->id('customer.'.$n), $reps[$n % 2], $context->now, $stage, $days, $stock ? $context->id('vehicle.'.$stock) : null, $text, $gross);
            if (3 === $n || 5 === $n) {
                $deal->setTradeIn(3 === $n ? new TradeIn(2017, 'Honda', 'Civic', 82000, 450000) : new TradeIn(2019, 'Toyota', 'Camry', 61000, null));
            }
            $this->em->persist($deal);
            $context->set('deal.'.$n, $deal->id());
        }

        $other = $this->walk($context->id('dealership.other'), $context->id('customer.other.1'), $context->id('user.other@mustang.test'), $context->now, DealStage::Engaged, 1, $context->id('vehicle.OM-2001'), null, null);
        $this->em->persist($other);
        $context->set('deal.other.1', $other->id());
    }

    private function walk(string $dealership, string $customer, string $assignee, \DateTimeImmutable $now, DealStage $stage, int $days, ?string $vehicleId, ?string $text, ?int $gross): Deal
    {
        $path = DealStage::Lost === $stage ? [DealStage::Engaged, DealStage::Lost] : array_values(array_filter(
            DealStage::cases(),
            static fn (DealStage $s): bool => DealStage::Lost !== $s && DealStage::New !== $s && $s->order() <= $stage->order(),
        ));
        $enteredAt = $now->modify(\sprintf('-%d days -2 hours', $days));
        $at = $enteredAt->modify(\sprintf('-%d days', \count($path)));
        $deal = Deal::open($dealership, $customer, $at, assignedUserId: $assignee, vehicleId: $vehicleId, vehicleText: $text, expectedGrossCents: null === $gross ? null : $gross * 100);
        foreach ($path as $i => $next) {
            $at = $i === \count($path) - 1 ? $enteredAt : $at->modify('+1 day');
            $deal->moveTo($next, DealStage::Lost === $next ? 'Bought elsewhere' : null, $at);
        }

        return $deal;
    }
}
