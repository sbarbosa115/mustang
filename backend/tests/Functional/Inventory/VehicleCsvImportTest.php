<?php

namespace App\Tests\Functional\Inventory;

use App\Inventory\Application\Command\ImportVehiclesCsv;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class VehicleCsvImportTest extends ApiTestCase
{
    private const HEADER = "stock_number,vin,year,make,model,trim,body,drivetrain,fuel,exterior_color,interior_color,mileage,condition,price,status,photo_url\n";

    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }
        foreach (glob(\dirname(__DIR__, 3).'/var/uploads/vehicles/*.csv') ?: [] as $stored) {
            @unlink($stored);
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function upload(string $contents, string $name = 'vehicles.csv'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'veh');
        file_put_contents($path, $contents);
        $this->temp[] = $path;
        $this->client->request('POST', '/api/v1/vehicles/import', [], ['file' => new UploadedFile($path, $name, 'text/csv', null, true)], ['HTTP_ACCEPT' => 'application/json']);

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    /** Runs what the worker would: every queued ImportVehiclesCsv. */
    private function runWorker(): void
    {
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof ImportVehiclesCsv) {
                static::getContainer()->get(\App\Inventory\Application\EventHandler\ImportVehiclesCsvHandler::class)($message);
            }
        }
        $transport->reset();
    }

    public function testTheSampleFileImportsEveryRow(): void
    {
        $this->signInAs('sales');
        $import = $this->upload((string) file_get_contents(\dirname(__DIR__, 3).'/public/samples/vehicles.csv'), 'sample.csv');

        $this->assertStatus(201);
        self::assertSame('pending', $import['status'], 'The request only queues the work.');
        self::assertSame('sample.csv', $import['file_name']);

        $this->runWorker();
        $done = $this->request('GET', '/api/v1/vehicles/import/'.$import['id']);
        self::assertSame('done', $done['status']);
        self::assertSame([4, 4, 0], [$done['rows_total'], $done['rows_ok'], $done['rows_failed']], 'The sample file we ship must import cleanly.');
        self::assertSame(3095000, (int) $this->db()->fetchOne('SELECT price_cents FROM vehicles WHERE stock_number = ?', ['SAMPLE-003']), 'A price like "30,950" is dollars.');
        self::assertSame('cpo', $this->db()->fetchOne('SELECT `condition` FROM vehicles WHERE stock_number = ?', ['SAMPLE-003']));
        self::assertNull($this->db()->fetchOne('SELECT price_cents FROM vehicles WHERE stock_number = ?', ['SAMPLE-004']), 'An empty price is "call for price".');
        self::assertSame($this->dealershipId(), $this->db()->fetchOne('SELECT dealership_id FROM vehicles WHERE stock_number = ?', ['SAMPLE-001']), 'Imported vehicles belong to the uploader\'s dealership.');
    }

    public function testBadRowsAreReportedAndTheRestStillLands(): void
    {
        $this->signInAs('sales');
        $import = $this->upload(self::HEADER
            ."GOOD-1,,2024,Ford,Ranger,,,,,,,100,new,30000,,\n"
            .",,2024,Ford,Ranger,,,,,,,100,new,30000,,\n"
            ."BAD-YEAR,,20x4,Ford,Ranger,,,,,,,100,new,30000,,\n"
            ."BAD-COND,,2024,Ford,Ranger,,,,,,,100,brandnew,30000,,\n"
            ."BAD-PRICE,,2024,Ford,Ranger,,,,,,,100,new,cheap,,\n"
            ."BAD-PHOTO,,2024,Ford,Ranger,,,,,,,100,new,30000,,ftp://x\n"
            ."\n"
            ."GOOD-2,,2023,Honda,Civic,,,,,,,5,used,20000.50,sold,\n");
        $this->runWorker();

        $done = $this->request('GET', '/api/v1/vehicles/import/'.$import['id']);
        self::assertSame('done', $done['status']);
        self::assertSame([7, 2, 5], [$done['rows_total'], $done['rows_ok'], $done['rows_failed']], 'The blank line is skipped, not counted.');
        self::assertSame([3, 4, 5, 6, 7], array_column($done['errors'], 'row'), 'Errors name the row as numbered in the file (header is row 1).');
        self::assertSame(['stock_number', 'year', 'condition', 'price', 'photo_url'], array_column($done['errors'], 'field'));
        self::assertSame(2000050, (int) $this->db()->fetchOne('SELECT price_cents FROM vehicles WHERE stock_number = ?', ['GOOD-2']));
        self::assertSame('sold', $this->db()->fetchOne('SELECT status FROM vehicles WHERE stock_number = ?', ['GOOD-2']));
        self::assertFalse($this->db()->fetchOne('SELECT id FROM vehicles WHERE stock_number = ?', ['BAD-YEAR']), 'A bad row creates nothing.');
    }

    public function testUploadingAnExistingStockNumberUpdatesItAndTwiceChangesNothing(): void
    {
        $this->signInAs('sales');
        $file = self::HEADER."MM-1001,,2026,Ford,F-150,XLT,,,,,,,new,99900,hold,\nDUP-1,,2024,Ford,Ranger,,,,,,,,new,1000,,\nDUP-1,,2024,Ford,Ranger,,,,,,,,new,2000,,\n";
        $this->upload($file);
        $this->runWorker();
        $this->upload($file);
        $this->runWorker();

        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM vehicles WHERE stock_number = ?', ['MM-1001']), 'No duplicate of a seeded vehicle.');
        self::assertSame([9990000, 'hold'], [(int) $this->db()->fetchOne('SELECT price_cents FROM vehicles WHERE stock_number = ?', ['MM-1001']), $this->db()->fetchOne('SELECT status FROM vehicles WHERE stock_number = ?', ['MM-1001'])]);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM vehicles WHERE stock_number = ?', ['DUP-1']), 'A stock number repeated in one file is one vehicle.');
        self::assertSame(200000, (int) $this->db()->fetchOne('SELECT price_cents FROM vehicles WHERE stock_number = ?', ['DUP-1']), 'The later row wins.');
    }

    public function testAFileWithoutRequiredColumnsFailsWithAReason(): void
    {
        $this->signInAs('sales');
        $import = $this->upload("stock_number,make\nX-1,Ford\n");
        $this->runWorker();

        $done = $this->request('GET', '/api/v1/vehicles/import/'.$import['id']);
        self::assertSame('failed', $done['status']);
        self::assertStringContainsString('year', $done['errors'][0]['message'], 'The message names the missing columns.');
        self::assertFalse($this->db()->fetchOne('SELECT id FROM vehicles WHERE stock_number = ?', ['X-1']));
    }

    public function testAnEmptyFileFailsAndABomAndCrLfAreAccepted(): void
    {
        $this->signInAs('sales');
        $empty = $this->upload('');
        $this->runWorker();
        $bom = $this->upload("\xEF\xBB\xBF".str_replace("\n", "\r\n", self::HEADER."BOM-1,,2024,Ford,Ranger,,,,,,,,new,1000,,\n"));
        $this->runWorker();

        self::assertSame('failed', $this->request('GET', '/api/v1/vehicles/import/'.$empty['id'])['status']);
        self::assertSame('done', $this->request('GET', '/api/v1/vehicles/import/'.$bom['id'])['status']);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM vehicles WHERE stock_number = ?', ['BOM-1']), 'Excel\'s BOM and CRLF line endings are fine.');
    }

    public function testOnlyCsvFilesAreAccepted(): void
    {
        $this->signInAs('sales');
        $this->upload('hello', 'photo.png');
        $this->assertStatus(422);

        $this->client->request('POST', '/api/v1/vehicles/import', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(422, 'No file is a validation error, not a crash.');
    }

    public function testALongFileNameIsShortenedNotACrash(): void
    {
        // Security audit 2026-10-18, finding 5: a client file name over 255 characters answered 500.
        $this->signInAs('sales');
        $import = $this->upload("stock_number,year,make,model,condition\n", str_repeat('a', 400).'.csv');

        $this->assertStatus(201);
        self::assertSame(255, mb_strlen((string) $this->db()->fetchOne('SELECT file_name FROM vehicle_imports WHERE id = ?', [$import['id']])));
    }

    public function testAnotherDealershipCannotReadAnImport(): void
    {
        $this->signInAs('sales');
        $import = $this->upload(self::HEADER);

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/vehicles/import/'.$import['id']);
    }
}
