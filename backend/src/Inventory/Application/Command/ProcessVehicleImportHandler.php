<?php

namespace App\Inventory\Application\Command;

use App\Inventory\Application\Import\VehicleRow;
use App\Inventory\Application\Port\VehicleCsvReader;
use App\Inventory\Domain\Model\Vehicle;
use App\Inventory\Domain\Model\VehicleImport;
use App\Inventory\Domain\Model\VehicleImportStatus;
use App\Inventory\Domain\Model\VehicleStatus;
use App\Inventory\Domain\Repository\VehicleImportRepository;
use App\Inventory\Domain\Repository\VehicleRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\InvalidValue;

/**
 * Reads a stored upload and adds or updates its vehicles. Runs on the worker, so nobody is signed in: the import carries its dealership. A bad row is reported and skipped, the
 * rest of the file still lands; a stock number already in the inventory is updated, never duplicated, so uploading the
 * same file twice changes nothing.
 */
final readonly class ProcessVehicleImportHandler implements CommandHandler
{
    private const int MAX_REPORTED_ERRORS = 200;

    public function __construct(
        private VehicleImportRepository $imports,
        private VehicleRepository $vehicles,
        private VehicleCsvReader $reader,
    ) {
    }

    public function __invoke(ProcessVehicleImport $command): void
    {
        $import = $this->imports->getForWorker($command->importId);
        if (VehicleImportStatus::Done === $import->status() || VehicleImportStatus::Failed === $import->status()) {
            return; // A retried message after the work was committed.
        }
        $import->begin();

        try {
            $file = $this->reader->read($import->filePath());
        } catch (InvalidValue $e) {
            $import->fail([['row' => 1, 'message' => $e->getMessage(), 'field' => null]]);

            return;
        }
        $missing = array_values(array_diff(VehicleRow::REQUIRED, $file->columns));
        if ([] !== $missing) {
            $import->fail([['row' => 1, 'message' => \sprintf('The file has no %s column.', implode(', ', $missing)), 'field' => $missing[0]]]);

            return;
        }

        $total = $ok = 0;
        $errors = [];
        $failed = 0;
        /** @var array<string, Vehicle> $seen stock numbers already handled in this file, flushed or not */
        $seen = [];
        foreach ($file->rows as $number => $values) {
            ++$total;
            try {
                $row = VehicleRow::fromArray($values);
                $key = mb_strtolower($row->stockNumber);
                $vehicle = $seen[$key] ?? $this->vehicles->findByStockNumber($import->dealershipId(), $row->stockNumber);
                if (null === $vehicle) {
                    $vehicle = $this->create($import, $row);
                    $this->vehicles->add($vehicle);
                } else {
                    $this->update($vehicle, $row);
                }
                $seen[$key] = $vehicle;
                ++$ok;
            } catch (InvalidValue $e) {
                ++$failed;
                if (\count($errors) < self::MAX_REPORTED_ERRORS) {
                    $field = $e->detail()['field'] ?? null;
                    $errors[] = ['row' => $number, 'message' => $e->getMessage(), 'field' => \is_string($field) && '' !== $field ? $field : null];
                }
            }
        }

        $import->finish($total, $ok, $failed, $errors);
    }

    private function create(VehicleImport $import, VehicleRow $row): Vehicle
    {
        return new Vehicle(
            $import->dealershipId(),
            $row->stockNumber,
            $row->year,
            $row->make,
            $row->model,
            $row->condition,
            $row->status ?? VehicleStatus::Available,
            $row->vin,
            $row->trim,
            $row->body,
            $row->drivetrain,
            $row->fuel,
            $row->exteriorColor,
            $row->interiorColor,
            $row->mileage,
            $row->priceCents,
            $row->photoUrl,
        );
    }

    private function update(Vehicle $vehicle, VehicleRow $row): void
    {
        $vehicle->revise(
            $row->year,
            $row->make,
            $row->model,
            $row->condition,
            $row->vin,
            $row->trim,
            $row->body,
            $row->drivetrain,
            $row->fuel,
            $row->exteriorColor,
            $row->interiorColor,
            $row->mileage,
            $row->priceCents,
            $row->photoUrl,
        );
        if (null !== $row->status) {
            $vehicle->changeStatus($row->status);
        }
    }
}
