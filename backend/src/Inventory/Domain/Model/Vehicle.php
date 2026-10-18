<?php

namespace App\Inventory\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A vehicle in the dealership's inventory (FR-21).
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicles')]
#[ORM\Index(name: 'idx_vehicles_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_vehicles_status', columns: ['dealership_id', 'status'])]
#[ORM\Index(name: 'idx_vehicles_make_model', columns: ['dealership_id', 'make', 'model'])]
#[ORM\Index(name: 'idx_vehicles_price', columns: ['dealership_id', 'price_cents'])]
#[ORM\UniqueConstraint(name: 'uniq_vehicles_stock', columns: ['dealership_id', 'stock_number'])]
#[ORM\HasLifecycleCallbacks]
class Vehicle
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'string', length: 40, name: 'stock_number')]
    private string $stockNumber;
    #[ORM\Column(type: 'integer', name: 'year')]
    private int $year;
    #[ORM\Column(type: 'string', length: 60, name: 'make')]
    private string $make;
    #[ORM\Column(type: 'string', length: 60, name: 'model')]
    private string $model;
    #[ORM\Column(type: 'string', length: 20, enumType: VehicleCondition::class, name: 'condition')]
    private VehicleCondition $condition;
    #[ORM\Column(type: 'string', length: 20, enumType: VehicleStatus::class, name: 'status')]
    private VehicleStatus $status;
    #[ORM\Column(type: 'string', length: 17, name: 'vin', nullable: true)]
    private ?string $vin;
    #[ORM\Column(type: 'string', length: 80, name: 'trim', nullable: true)]
    private ?string $trim;
    #[ORM\Column(type: 'string', length: 40, name: 'body', nullable: true)]
    private ?string $body;
    #[ORM\Column(type: 'string', length: 20, name: 'drivetrain', nullable: true)]
    private ?string $drivetrain;
    #[ORM\Column(type: 'string', length: 20, name: 'fuel', nullable: true)]
    private ?string $fuel;
    #[ORM\Column(type: 'string', length: 40, name: 'exterior_color', nullable: true)]
    private ?string $exteriorColor;
    #[ORM\Column(type: 'string', length: 40, name: 'interior_color', nullable: true)]
    private ?string $interiorColor;
    #[ORM\Column(type: 'integer', name: 'mileage', nullable: true)]
    private ?int $mileage;
    #[ORM\Column(type: 'integer', name: 'price_cents', nullable: true)]
    private ?int $priceCents;
    #[ORM\Column(type: 'string', length: 500, name: 'photo_url', nullable: true)]
    private ?string $photoUrl;
    #[ORM\Column(type: 'text', name: 'description', nullable: true)]
    private ?string $description;

    public function __construct(
        string $dealershipId,
        string $stockNumber,
        int $year,
        string $make,
        string $model,
        VehicleCondition $condition,
        VehicleStatus $status = VehicleStatus::Available,
        ?string $vin = null,
        ?string $trim = null,
        ?string $body = null,
        ?string $drivetrain = null,
        ?string $fuel = null,
        ?string $exteriorColor = null,
        ?string $interiorColor = null,
        ?int $mileage = null,
        ?int $priceCents = null,
        ?string $photoUrl = null,
        ?string $description = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->stockNumber = $stockNumber;
        $this->year = $year;
        $this->make = $make;
        $this->model = $model;
        $this->condition = $condition;
        $this->status = $status;
        $this->vin = $vin;
        $this->trim = $trim;
        $this->body = $body;
        $this->drivetrain = $drivetrain;
        $this->fuel = $fuel;
        $this->exteriorColor = $exteriorColor;
        $this->interiorColor = $interiorColor;
        $this->mileage = $mileage;
        $this->priceCents = $priceCents;
        $this->photoUrl = $photoUrl;
        $this->description = $description;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function stockNumber(): string
    {
        return $this->stockNumber;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function make(): string
    {
        return $this->make;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function condition(): VehicleCondition
    {
        return $this->condition;
    }

    public function status(): VehicleStatus
    {
        return $this->status;
    }

    public function vin(): ?string
    {
        return $this->vin;
    }

    public function trim(): ?string
    {
        return $this->trim;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function drivetrain(): ?string
    {
        return $this->drivetrain;
    }

    public function fuel(): ?string
    {
        return $this->fuel;
    }

    public function exteriorColor(): ?string
    {
        return $this->exteriorColor;
    }

    public function interiorColor(): ?string
    {
        return $this->interiorColor;
    }

    public function mileage(): ?int
    {
        return $this->mileage;
    }

    public function priceCents(): ?int
    {
        return $this->priceCents;
    }

    public function photoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
