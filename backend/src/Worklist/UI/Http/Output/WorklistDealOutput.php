<?php

namespace App\Worklist\UI\Http\Output;

use OpenApi\Attributes as OA;

final readonly class WorklistDealOutput
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
