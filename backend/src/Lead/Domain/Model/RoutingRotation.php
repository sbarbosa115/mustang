<?php

namespace App\Lead\Domain\Model;

use App\Shared\Domain\Error\InvalidValue;
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

    /**
     * The reps in rotation order.
     *
     * @return list<string>
     */
    public function userIds(): array
    {
        return array_values(array_map(strval(...), $this->userIdsJson));
    }

    /**
     * Sets a new order; the rotation starts again from its first rep.
     *
     * @param list<string> $userIds
     *
     * @throws InvalidValue routing_duplicate_user when a rep is listed twice
     */
    public function reorder(array $userIds): void
    {
        if (\count($userIds) !== \count(array_unique($userIds))) {
            throw new InvalidValue('routing_duplicate_user', 'A rep can be in the rotation only once.');
        }
        $this->userIdsJson = $userIds;
        $this->nextIndex = 0;
    }

    /**
     * Whose turn it is, skipping reps not in $eligible (inactive ones), without taking the turn.
     *
     * @param list<string> $eligible
     */
    public function peek(array $eligible): ?string
    {
        $position = $this->nextPosition($eligible);

        return null === $position ? null : $this->userIds()[$position];
    }

    /**
     * Takes the turn: answers the rep whose turn it is and moves the rotation past them.
     *
     * @param list<string> $eligible
     */
    public function take(array $eligible): ?string
    {
        $position = $this->nextPosition($eligible);
        if (null === $position) {
            return null;
        }
        $ids = $this->userIds();
        $this->nextIndex = ($position + 1) % \count($ids);

        return $ids[$position];
    }

    /**
     * @param list<string> $eligible
     */
    private function nextPosition(array $eligible): ?int
    {
        $ids = $this->userIds();
        $count = \count($ids);
        for ($step = 0; $step < $count; ++$step) {
            $position = (max(0, $this->nextIndex) + $step) % $count;
            if (\in_array($ids[$position], $eligible, true)) {
                return $position;
            }
        }

        return null;
    }
}
