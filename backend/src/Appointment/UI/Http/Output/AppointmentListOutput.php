<?php

namespace App\Appointment\UI\Http\Output;

final readonly class AppointmentListOutput
{
    /**
     * @param list<AppointmentOutput> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
