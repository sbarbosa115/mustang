<?php

namespace App\Lead\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * The round-robin order of reps for new leads; one per dealership.
 */
#[ORM\Entity]
#[ORM\Table(name: 'routing_rotations')]
#[ORM\Index(name: 'idx_routing_rotations_dealership', columns: ['dealership_id'])]
#[ORM\UniqueConstraint(name: 'uniq_rotation_dealership', columns: ['dealership_id'])]
#[ORM\HasLifecycleCallbacks]
class RoutingRotation
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    /** @var array<mixed> */
    #[ORM\Column(type: 'json', name: 'user_ids_json')]
    private array $userIdsJson;
    #[ORM\Column(type: 'integer', name: 'next_index')]
    private int $nextIndex;

    /**
     * @param array<mixed> $userIdsJson
     */
    public function __construct(
        string $dealershipId,
        array $userIdsJson = [],
        int $nextIndex = 0,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->userIdsJson = $userIdsJson;
        $this->nextIndex = $nextIndex;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    /** @return array<mixed> */
    public function userIdsJson(): array
    {
        return $this->userIdsJson;
    }

    public function nextIndex(): int
    {
        return $this->nextIndex;
    }
}
