<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Compliance\Domain\Model\Suppression;
use App\Compliance\Domain\Repository\SuppressionList;
use App\Shared\Domain\Channel;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSuppressionList implements SuppressionList
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $contactValue, Channel $channel): ?Suppression
    {
        return $this->em->getRepository(Suppression::class)->findOneBy(['contactValue' => $contactValue, 'channel' => $channel]);
    }

    public function add(Suppression $suppression): void
    {
        $this->em->persist($suppression);
    }

    public function remove(Suppression $suppression): void
    {
        $this->em->remove($suppression);
    }
}
