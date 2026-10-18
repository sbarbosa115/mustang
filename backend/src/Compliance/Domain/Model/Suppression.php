<?php

namespace App\Compliance\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A contact that opted out: blocked for every dealership (FR-12).
 */
#[ORM\Entity]
#[ORM\Table(name: 'suppressions')]
#[ORM\UniqueConstraint(name: 'uniq_suppressions_contact', columns: ['contact_value', 'channel'])]
#[ORM\HasLifecycleCallbacks]
class Suppression
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'string', length: 180, name: 'contact_value')]
    private string $contactValue;
    #[ORM\Column(type: 'string', length: 20, enumType: \App\Shared\Domain\Channel::class, name: 'channel')]
    private \App\Shared\Domain\Channel $channel;
    #[ORM\Column(type: 'string', length: 120, name: 'reason')]
    private string $reason;

    public function __construct(
        string $contactValue,
        \App\Shared\Domain\Channel $channel,
        string $reason,
    ) {
        $this->id = Id::new();
        $this->contactValue = $contactValue;
        $this->channel = $channel;
        $this->reason = $reason;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function contactValue(): string
    {
        return $this->contactValue;
    }

    public function channel(): \App\Shared\Domain\Channel
    {
        return $this->channel;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
