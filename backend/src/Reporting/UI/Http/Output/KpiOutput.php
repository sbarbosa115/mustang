<?php

namespace App\Reporting\UI\Http\Output;

use OpenApi\Attributes as OA;

final readonly class KpiOutput
{
    public function __construct(
        #[OA\Property(enum: ['first_response_minutes', 'leads_followed', 'lead_to_contact', 'contact_to_appointment', 'appointment_to_show', 'show_to_sale', 'units_per_salesperson'])]
        public string $code,
        #[OA\Property(enum: ['minutes', 'percent', 'units'])]
        public string $unit,
        public ?float $value = null,
        public ?float $previousValue = null,
    ) {
    }
}
