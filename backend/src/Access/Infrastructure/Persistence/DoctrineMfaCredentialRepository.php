<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Model\MfaCredential;
use App\Access\Domain\Repository\MfaCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMfaCredentialRepository implements MfaCredentialRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findForUser(string $userId): ?MfaCredential
    {
        return $this->em->getRepository(MfaCredential::class)->findOneBy(['userId' => $userId]);
    }

    public function add(MfaCredential $credential): void
    {
        $this->em->persist($credential);
    }

    public function remove(MfaCredential $credential): void
    {
        $this->em->remove($credential);
    }
}
