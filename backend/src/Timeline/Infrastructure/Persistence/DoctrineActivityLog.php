<?php

namespace App\Timeline\Infrastructure\Persistence;

use App\Timeline\Domain\Model\Activity;
use App\Timeline\Domain\Repository\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineActivityLog implements ActivityLog
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function append(Activity $entry): void
    {
        $this->em->persist($entry);
    }
}
