<?php

namespace App\Customer\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * The customer's open deal, if any.
 */
final readonly class OpenDealOutput
{
    public function __construct(
        public string $id,
        #[OA\Property(enum: ['new', 'engaged', 'appointment', 'visit', 'proposal', 'sold', 'delivered', 'lost'])]
        public string $stage,
        public int $daysInStage,
        public ?string $vehicleText = null,
    ) {
    }
}
