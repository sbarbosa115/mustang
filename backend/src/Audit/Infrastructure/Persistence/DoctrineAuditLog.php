<?php

namespace App\Audit\Infrastructure\Persistence;

use App\Audit\Domain\Model\AuditEntry;
use App\Audit\Domain\Repository\AuditLog;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAuditLog implements AuditLog
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function append(AuditEntry $entry): void
    {
        $this->em->persist($entry);
    }
}
