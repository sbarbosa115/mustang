<?php

namespace App\Customer\Application\Command;

use App\Customer\Domain\Repository\CustomerRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Domain\Error\InvalidValue;

final readonly class AddOwnedVehicleHandler implements CommandHandler
{
    public function __construct(private CustomerRepository $customers)
    {
    }

    public function __invoke(AddOwnedVehicle $command): string
    {
        $customer = $this->customers->get($command->customerId);
        $vehicle = $customer->addOwnedVehicle(
            $command->year,
            $command->make,
            $command->model,
            $command->trim,
            $command->vin,
            self::date($command->purchasedAt),
        );
        $customer->touch();

        return $vehicle->id();
    }

    private static function date(?string $raw): ?\DateTimeImmutable
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }
        try {
            return new \DateTimeImmutable(trim($raw), new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new InvalidValue('invalid_date', \sprintf('"%s" is not a date.', $raw), ['value' => $raw]);
        }
    }
}
