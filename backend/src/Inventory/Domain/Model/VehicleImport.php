<?php

namespace App\Inventory\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A vehicles CSV upload and its result.
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicle_imports')]
#[ORM\Index(name: 'idx_vehicle_imports_dealership', columns: ['dealership_id'])]
#[ORM\HasLifecycleCallbacks]
class VehicleImport
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'guid', name: 'uploaded_by_user_id')]
    private string $uploadedByUserId;
    #[ORM\Column(type: 'string', length: 255, name: 'file_name')]
    private string $fileName;
    #[ORM\Column(type: 'string', length: 500, name: 'file_path')]
    private string $filePath;
    #[ORM\Column(type: 'string', length: 20, enumType: VehicleImportStatus::class, name: 'status')]
    private VehicleImportStatus $status;
    #[ORM\Column(type: 'integer', name: 'rows_total')]
    private int $rowsTotal;
    #[ORM\Column(type: 'integer', name: 'rows_ok')]
    private int $rowsOk;
    #[ORM\Column(type: 'integer', name: 'rows_failed')]
    private int $rowsFailed;
    /** @var array<mixed> */
    #[ORM\Column(type: 'json', name: 'errors_json')]
    private array $errorsJson;

    /**
     * @param array<mixed> $errorsJson
     */
    public function __construct(
        string $dealershipId,
        string $uploadedByUserId,
        string $fileName,
        string $filePath,
        VehicleImportStatus $status = VehicleImportStatus::Pending,
        int $rowsTotal = 0,
        int $rowsOk = 0,
        int $rowsFailed = 0,
        array $errorsJson = [],
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->uploadedByUserId = $uploadedByUserId;
        $this->fileName = $fileName;
        $this->filePath = $filePath;
        $this->status = $status;
        $this->rowsTotal = $rowsTotal;
        $this->rowsOk = $rowsOk;
        $this->rowsFailed = $rowsFailed;
        $this->errorsJson = $errorsJson;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function uploadedByUserId(): string
    {
        return $this->uploadedByUserId;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function status(): VehicleImportStatus
    {
        return $this->status;
    }

    public function rowsTotal(): int
    {
        return $this->rowsTotal;
    }

    public function rowsOk(): int
    {
        return $this->rowsOk;
    }

    public function rowsFailed(): int
    {
        return $this->rowsFailed;
    }

    /** @return array<mixed> */
    public function errorsJson(): array
    {
        return $this->errorsJson;
    }
}
