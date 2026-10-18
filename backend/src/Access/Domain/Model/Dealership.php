<?php

namespace App\Access\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A rooftop: the tenant every other record belongs to.
 */
#[ORM\Entity]
#[ORM\Table(name: 'dealerships')]
#[ORM\HasLifecycleCallbacks]
class Dealership
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'string', length: 120, name: 'name')]
    private string $name;
    #[ORM\Column(type: 'string', length: 64, name: 'timezone')]
    private string $timezone;
    #[ORM\Column(type: 'integer', name: 'average_gross_cents')]
    private int $averageGrossCents;

    public function __construct(
        string $name,
        string $timezone = 'America/New_York',
        int $averageGrossCents = 300000,
    ) {
        $this->id = Id::new();
        $this->name = $name;
        $this->timezone = $timezone;
        $this->averageGrossCents = $averageGrossCents;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function averageGrossCents(): int
    {
        return $this->averageGrossCents;
    }
}
