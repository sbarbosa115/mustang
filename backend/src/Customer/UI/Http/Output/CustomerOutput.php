<?php

namespace App\Customer\UI\Http\Output;

use App\Shared\UI\Http\Output\ChannelConsentOutput;

final readonly class CustomerOutput
{
    /**
     * @param list<ContactOutput>      $contacts
     * @param list<OwnedVehicleOutput> $ownedVehicles
     */
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $name,
        public string $source,
        public array $contacts,
        public array $ownedVehicles,
        public AssignmentsOutput $assignments,
        public ChannelConsentOutput $consent,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\App\Shared\Domain\Channel $preferredChannel = null,
        public ?OpenDealOutput $openDeal = null,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }
}
