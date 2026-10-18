<?php

namespace App\Inventory\UI\Http;

use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Model\VehicleImport;
use App\Inventory\UI\Http\Output\FacetOutput;
use App\Inventory\UI\Http\Output\VehicleFacetsOutput;
use App\Inventory\UI\Http\Output\VehicleImportOutput;
use App\Inventory\UI\Http\Output\VehicleOutput;
use App\Shared\UI\Http\Output\RowErrorOutput;

/**
 * Builds the inventory responses.
 */
final class VehiclePresenter
{
    public function vehicle(Vehicle $v): VehicleOutput
    {
        return new VehicleOutput(
            $v->id(),
            $v->stockNumber(),
            $v->year(),
            $v->make(),
            $v->model(),
            \sprintf('%d %s %s', $v->year(), $v->make(), $v->model()),
            $v->condition(),
            $v->status(),
            $v->createdAt(),
            $v->vin(),
            $v->trim(),
            $v->body(),
            $v->drivetrain(),
            $v->fuel(),
            $v->exteriorColor(),
            $v->interiorColor(),
            $v->mileage(),
            $v->priceCents(),
            $v->photoUrl(),
            $v->description(),
        );
    }

    /**
     * @param list<Vehicle> $vehicles
     *
     * @return list<VehicleOutput>
     */
    public function vehicles(array $vehicles): array
    {
        return array_map($this->vehicle(...), $vehicles);
    }

    /**
     * @param array<string, mixed> $facets the shape of VehicleQueries::facets()
     */
    public function facets(array $facets): VehicleFacetsOutput
    {
        $list = static fn (string $key): array => array_map(
            static fn (array $f): FacetOutput => new FacetOutput($f['value'], $f['count']),
            $facets[$key],
        );

        return new VehicleFacetsOutput(
            $list('makes'),
            $list('models'),
            $list('bodies'),
            $list('drivetrains'),
            $list('fuels'),
            $list('colors'),
            $facets['year_min'],
            $facets['year_max'],
            $facets['price_min_cents'],
            $facets['price_max_cents'],
        );
    }

    public function import(VehicleImport $import): VehicleImportOutput
    {
        $errors = [];
        foreach ($import->errorsJson() as $error) {
            if (\is_array($error)) {
                $errors[] = new RowErrorOutput((int) ($error['row'] ?? 0), (string) ($error['message'] ?? ''), isset($error['field']) ? (string) $error['field'] : null);
            }
        }

        return new VehicleImportOutput(
            $import->id(),
            $import->fileName(),
            $import->status(),
            $import->rowsTotal(),
            $import->rowsOk(),
            $import->rowsFailed(),
            $errors,
            $import->createdAt(),
        );
    }
}
