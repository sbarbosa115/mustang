<?php

namespace App\DataImport\Domain\Model;

use App\Shared\Domain\Id;
use App\Shared\Domain\Model\Timestamps;
use Doctrine\ORM\Mapping as ORM;

/**
 * A customers or leads spreadsheet import and its result.
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
#[ORM\Index(name: 'idx_import_jobs_dealership', columns: ['dealership_id'])]
#[ORM\Index(name: 'idx_import_jobs_created', columns: ['dealership_id', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class ImportJob
{
    use Timestamps;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;
    #[ORM\Column(type: 'guid', name: 'dealership_id')]
    private string $dealershipId;
    #[ORM\Column(type: 'string', length: 20, enumType: ImportType::class, name: 'type')]
    private ImportType $type;
    #[ORM\Column(type: 'guid', name: 'uploaded_by_user_id')]
    private string $uploadedByUserId;
    #[ORM\Column(type: 'string', length: 255, name: 'file_name')]
    private string $fileName;
    #[ORM\Column(type: 'string', length: 500, name: 'file_path')]
    private string $filePath;
    #[ORM\Column(type: 'string', length: 20, enumType: ImportStatus::class, name: 'status')]
    private ImportStatus $status;
    #[ORM\Column(type: 'integer', name: 'rows_total')]
    private int $rowsTotal;
    #[ORM\Column(type: 'integer', name: 'rows_created')]
    private int $rowsCreated;
    #[ORM\Column(type: 'integer', name: 'rows_updated')]
    private int $rowsUpdated;
    #[ORM\Column(type: 'integer', name: 'rows_skipped')]
    private int $rowsSkipped;
    #[ORM\Column(type: 'integer', name: 'rows_failed')]
    private int $rowsFailed;
    /** @var array<mixed> */
    #[ORM\Column(type: 'json', name: 'errors_json')]
    private array $errorsJson;
    #[ORM\Column(type: 'datetime_immutable', name: 'finished_at', nullable: true)]
    private ?\DateTimeImmutable $finishedAt;

    /**
     * @param array<mixed> $errorsJson
     */
    public function __construct(
        string $dealershipId,
        ImportType $type,
        string $uploadedByUserId,
        string $fileName,
        string $filePath,
        ImportStatus $status = ImportStatus::Pending,
        int $rowsTotal = 0,
        int $rowsCreated = 0,
        int $rowsUpdated = 0,
        int $rowsSkipped = 0,
        int $rowsFailed = 0,
        array $errorsJson = [],
        ?\DateTimeImmutable $finishedAt = null,
    ) {
        $this->id = Id::new();
        $this->dealershipId = $dealershipId;
        $this->type = $type;
        $this->uploadedByUserId = $uploadedByUserId;
        $this->fileName = $fileName;
        $this->filePath = $filePath;
        $this->status = $status;
        $this->rowsTotal = $rowsTotal;
        $this->rowsCreated = $rowsCreated;
        $this->rowsUpdated = $rowsUpdated;
        $this->rowsSkipped = $rowsSkipped;
        $this->rowsFailed = $rowsFailed;
        $this->errorsJson = $errorsJson;
        $this->finishedAt = $finishedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function dealershipId(): string
    {
        return $this->dealershipId;
    }

    public function type(): ImportType
    {
        return $this->type;
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

    public function status(): ImportStatus
    {
        return $this->status;
    }

    public function rowsTotal(): int
    {
        return $this->rowsTotal;
    }

    public function rowsCreated(): int
    {
        return $this->rowsCreated;
    }

    public function rowsUpdated(): int
    {
        return $this->rowsUpdated;
    }

    public function rowsSkipped(): int
    {
        return $this->rowsSkipped;
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

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
