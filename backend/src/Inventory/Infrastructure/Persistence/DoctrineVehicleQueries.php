<?php

namespace App\Inventory\Infrastructure\Persistence;

use App\Inventory\Application\Query\VehicleFilter;
use App\Inventory\Application\Query\VehicleQueries;
use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Repository\VehicleRepository;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Infrastructure\Persistence\Like;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineVehicleQueries implements VehicleQueries
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenant,
        private VehicleRepository $vehicles,
    ) {
    }

    public function page(VehicleFilter $filter, int $offset, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Vehicle::class, 'v')
            ->where('v.dealershipId = :dealership')
            ->setParameter('dealership', $this->tenant->dealershipId());
        $this->filter($qb, $filter);

        $total = (int) (clone $qb)->select('COUNT(v.id)')->getQuery()->getSingleScalarResult();
        $qb->select('v');
        // A vehicle without a price sorts after the priced ones either way ("call for price" is never the cheapest).
        match ($filter->sort) {
            'price_asc' => $qb->addSelect('CASE WHEN v.priceCents IS NULL THEN 1 ELSE 0 END AS HIDDEN noPrice')->orderBy('noPrice', 'ASC')->addOrderBy('v.priceCents', 'ASC'),
            'price_desc' => $qb->addSelect('CASE WHEN v.priceCents IS NULL THEN 1 ELSE 0 END AS HIDDEN noPrice')->orderBy('noPrice', 'ASC')->addOrderBy('v.priceCents', 'DESC'),
            'year_desc' => $qb->orderBy('v.year', 'DESC')->addOrderBy('v.make', 'ASC')->addOrderBy('v.model', 'ASC'),
            'mileage_asc' => $qb->addSelect('CASE WHEN v.mileage IS NULL THEN 1 ELSE 0 END AS HIDDEN noMileage')->orderBy('noMileage', 'ASC')->addOrderBy('v.mileage', 'ASC'),
            default => $qb->orderBy('v.createdAt', 'DESC'),
        };
        /** @var list<Vehicle> $items */
        $items = $qb->addOrderBy('v.stockNumber', 'ASC')
            ->setFirstResult($offset)->setMaxResults($limit)
            ->getQuery()->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function byId(string $id): Vehicle
    {
        return $this->vehicles->get($id);
    }

    public function facets(): array
    {
        $dealership = $this->tenant->dealershipId();
        $facet = fn (string $column): array => $this->count($dealership, $column);
        $range = $this->em->getConnection()->fetchAssociative(
            'SELECT MIN(year) AS year_min, MAX(year) AS year_max, MIN(price_cents) AS price_min, MAX(price_cents) AS price_max
             FROM vehicles WHERE dealership_id = :dealership',
            ['dealership' => $dealership],
        ) ?: [];

        return [
            'makes' => $facet('make'),
            'models' => $facet('model'),
            'bodies' => $facet('body'),
            'drivetrains' => $facet('drivetrain'),
            'fuels' => $facet('fuel'),
            'colors' => $facet('exterior_color'),
            'year_min' => isset($range['year_min']) ? (int) $range['year_min'] : null,
            'year_max' => isset($range['year_max']) ? (int) $range['year_max'] : null,
            'price_min_cents' => isset($range['price_min']) ? (int) $range['price_min'] : null,
            'price_max_cents' => isset($range['price_max']) ? (int) $range['price_max'] : null,
        ];
    }

    /**
     * @param 'make'|'model'|'body'|'drivetrain'|'fuel'|'exterior_color' $column a fixed set, never user input
     *
     * @return list<array{value: string, count: int}>
     */
    private function count(string $dealership, string $column): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            \sprintf(
                'SELECT %1$s AS val, COUNT(*) AS n FROM vehicles
                 WHERE dealership_id = :dealership AND %1$s IS NOT NULL AND %1$s <> \'\'
                 GROUP BY %1$s ORDER BY %1$s ASC',
                $column,
            ),
            ['dealership' => $dealership],
        );

        return array_map(static fn (array $r): array => ['value' => (string) $r['val'], 'count' => (int) $r['n']], $rows);
    }

    private function filter(QueryBuilder $qb, VehicleFilter $f): void
    {
        if (null !== $f->term) {
            // Every word must match somewhere: "2022 f-150", "silverado lt", a stock number or a VIN.
            foreach (preg_split('/\s+/', $f->term, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $i => $word) {
                $qb->andWhere(\sprintf(
                    "(v.stockNumber LIKE :w%1\$d OR v.vin LIKE :w%1\$d OR v.make LIKE :w%1\$d OR v.model LIKE :w%1\$d OR v.trim LIKE :w%1\$d OR CONCAT(v.year, '') LIKE :w%1\$d OR v.exteriorColor LIKE :w%1\$d OR v.body LIKE :w%1\$d)",
                    $i,
                ))->setParameter('w'.$i, Like::contains($word));
            }
        }
        $eq = [
            'status' => $f->status, 'condition' => $f->condition, 'make' => $f->make, 'model' => $f->model,
            'exteriorColor' => $f->color, 'body' => $f->body, 'drivetrain' => $f->drivetrain, 'fuel' => $f->fuel,
        ];
        foreach ($eq as $field => $value) {
            if (null !== $value) {
                $qb->andWhere(\sprintf('v.%1$s = :%1$s', $field))->setParameter($field, $value);
            }
        }
        $range = [
            ['v.year >= :yearMin', 'yearMin', $f->yearMin], ['v.year <= :yearMax', 'yearMax', $f->yearMax],
            ['v.priceCents >= :priceMin', 'priceMin', $f->priceMinCents], ['v.priceCents <= :priceMax', 'priceMax', $f->priceMaxCents],
            ['v.mileage <= :mileageMax', 'mileageMax', $f->mileageMax],
        ];
        foreach ($range as [$dql, $name, $value]) {
            if (null !== $value) {
                $qb->andWhere($dql)->setParameter($name, $value);
            }
        }
    }
}
