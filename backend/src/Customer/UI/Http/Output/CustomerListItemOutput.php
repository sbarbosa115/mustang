<?php

namespace App\Customer\UI\Http\Output;

use App\Shared\UI\Http\Output\UserRefOutput;
use OpenApi\Attributes as OA;

final readonly class CustomerListItemOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $firstName,
        public string $lastName,
        public bool $archived,
        public \DateTimeImmutable $updatedAt,
        public ?string $primaryPhone = null,
        public ?string $primaryEmail = null,
        public ?UserRefOutput $sales1 = null,
        #[OA\Property(enum: ['new', 'engaged', 'appointment', 'visit', 'proposal', 'sold', 'delivered', 'lost'])]
        public ?string $openDealStage = null,
    ) {
    }
}
