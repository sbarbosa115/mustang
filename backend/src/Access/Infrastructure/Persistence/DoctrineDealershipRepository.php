<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Model\Dealership;
use App\Access\Domain\Repository\DealershipRepository;
use App\Shared\Domain\Error\NotFound;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDealershipRepository implements DealershipRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function get(string $id): Dealership
    {
        return $this->em->find(Dealership::class, $id)
            ?? throw new NotFound('dealership_not_found', 'The dealership does not exist.', ['id' => $id]);
    }

    public function add(Dealership $dealership): void
    {
        $this->em->persist($dealership);
    }
}
