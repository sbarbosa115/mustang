<?php

namespace App\Inventory\Infrastructure\Csv;

use App\Inventory\Application\Port\VehicleCsvFile;
use App\Inventory\Application\Port\VehicleCsvReader as VehicleCsvReaderPort;
use App\Shared\Domain\Error\InvalidValue;

/**
 * Reads a UTF-8 comma-separated file with a header row, lazily, so a large file does not sit in memory.
 */
final class VehicleCsvReader implements VehicleCsvReaderPort
{
    public function read(string $path): VehicleCsvFile
    {
        $handle = @fopen($path, 'r');
        if (false === $handle) {
            throw new InvalidValue('csv_unreadable', 'The file could not be opened.');
        }
        $header = $this->nextRecord($handle);
        if (null === $header) {
            fclose($handle);
            throw new InvalidValue('csv_empty', 'The file is empty: it needs a header row.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        $columns = array_map(static fn (string $c): string => strtolower(trim($c)), $header);
        $this->guardEncoding($header);

        return new VehicleCsvFile($columns, $this->rows($handle, $columns));
    }

    /**
     * @param resource     $handle
     * @param list<string> $columns
     *
     * @return \Generator<int, array<string, string>>
     */
    private function rows($handle, array $columns): \Generator
    {
        try {
            $line = 1;
            while (null !== ($record = $this->nextRecord($handle))) {
                ++$line;
                if ([] === array_filter($record, static fn (string $v): bool => '' !== trim($v))) {
                    continue;
                }
                if (!mb_check_encoding(implode('', $record), 'UTF-8')) {
                    yield $line => ['__invalid_encoding' => '1'];
                    continue;
                }
                $row = [];
                foreach ($columns as $i => $column) {
                    if ('' !== $column) {
                        $row[$column] = $record[$i] ?? '';
                    }
                }
                yield $line => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return list<string>|null
     */
    private function nextRecord($handle): ?array
    {
        $record = fgetcsv($handle, 0, ',', '"', '');
        if (false === $record) {
            return null;
        }

        return array_map(static fn (?string $v): string => (string) $v, $record);
    }

    /**
     * @param list<string> $header
     */
    private function guardEncoding(array $header): void
    {
        if (!mb_check_encoding(implode('', $header), 'UTF-8')) {
            throw new InvalidValue('csv_not_utf8', 'The file is not UTF-8 text.');
        }
    }
}
