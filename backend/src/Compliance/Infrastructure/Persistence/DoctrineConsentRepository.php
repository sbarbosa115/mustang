<?php

namespace App\Compliance\Infrastructure\Persistence;

use App\Compliance\Domain\Model\Consent;
use App\Compliance\Domain\Model\ConsentState;
use App\Compliance\Domain\Repository\ConsentRepository;
use App\Shared\Domain\Channel;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Id;
use App\Shared\Infrastructure\Persistence\TenantScopedRepository;

/**
 * @extends TenantScopedRepository<Consent>
 */
final class DoctrineConsentRepository extends TenantScopedRepository implements ConsentRepository
{
    protected function entityClass(): string
    {
        return Consent::class;
    }

    protected function notFoundCode(): string
    {
        return 'consent_not_found';
    }

    public function get(string $id): Consent
    {
        return $this->getScoped($id);
    }

    public function getIn(string $dealershipId, string $id): Consent
    {
        $consent = Id::isValid($id)
            ? $this->em->getRepository(Consent::class)->findOneBy(['id' => $id, 'dealershipId' => $dealershipId])
            : null;

        return $consent ?? throw new NotFound($this->notFoundCode(), 'The record does not exist.', ['id' => $id]);
    }

    public function grantedFor(string $dealershipId, string $contactValue, Channel $channel): array
    {
        return $this->em->getRepository(Consent::class)->findBy(
            ['dealershipId' => $dealershipId, 'contactValue' => $contactValue, 'channel' => $channel, 'state' => ConsentState::Granted],
            ['capturedAt' => 'ASC'],
        );
    }

    public function add(Consent $consent): void
    {
        $this->persist($consent);
    }
}
