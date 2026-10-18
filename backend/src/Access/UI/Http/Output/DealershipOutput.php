<?php

namespace App\Access\UI\Http\Output;

use App\Access\Domain\Model\Dealership;

final readonly class DealershipOutput
{
    public function __construct(
        public string $id,
        public string $name,
        public string $timezone,
        public int $averageGrossCents,
    ) {
    }

    public static function from(Dealership $dealership): self
    {
        return new self($dealership->id(), $dealership->name(), $dealership->timezone(), $dealership->averageGrossCents());
    }
}
