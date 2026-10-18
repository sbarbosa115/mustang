<?php

namespace App\Worklist\UI\Http\Output;

use OpenApi\Attributes as OA;

final readonly class WorklistAppointmentOutput
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $scheduledAt,
        #[OA\Property(enum: ['scheduled', 'confirmed', 'shown', 'no_show', 'cancelled'])]
        public string $status,
    ) {
    }
}
