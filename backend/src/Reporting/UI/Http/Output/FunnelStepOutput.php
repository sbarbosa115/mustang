<?php

namespace App\Reporting\UI\Http\Output;

use OpenApi\Attributes as OA;

final readonly class FunnelStepOutput
{
    public function __construct(
        #[OA\Property(enum: ['leads', 'contacted', 'appointments', 'shows', 'sales'])]
        public string $code,
        public int $count,
    ) {
    }
}
