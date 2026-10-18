<?php

namespace App\Tests\Functional\DataImport;

final class ImportApiTest extends ImportTestCase
{
    private const CSV = "first_name,last_name,phone,email\nApi,Test,813-555-7951,\n";

    public function testOnlyManagersAndAdminsImport(): void
    {
        foreach (['sales', 'bdc'] as $role) {
            $this->signInAs($role);
            $this->upload('customers', self::CSV);
            $this->assertStatus(403, $role.' cannot upload.');
            $this->request('GET', '/api/v1/imports');
            $this->assertStatus(403, $role.' cannot list imports: their errors name customers.');
        }
        foreach (['manager', 'admin'] as $role) {
            $this->signInAs($role);
            $job = $this->upload('customers', self::CSV);
            $this->assertStatus(201, $role.' can upload.');
            self::assertSame(['customers', 'pending', 'import.csv'], [$job['type'], $job['status'], $job['file_name']], 'The request only queues the work.');
            self::assertSame($this->userId($role), $job['uploaded_by']['id']);
        }
    }

    public function testAFileOverTenMegabytesIs413(): void
    {
        $this->signInAs('manager');
        $body = $this->upload('customers', "first_name,last_name,phone,email\n".str_repeat("Big,File,813-555-7952,big@example.com\n", 300_000));

        $this->assertError(413, 'file_too_large', $body);
    }

    public function testTheTypeAndTheFileAreChecked(): void
    {
        $this->signInAs('manager');
        $body = $this->upload('vehicles', self::CSV);
        $this->assertError(422, 'invalid_import_type', $body);
        self::assertSame('type', $body['detail']['field'] ?? null);

        $body = $this->upload('customers', 'hello', 'photo.png');
        $this->assertError(422, 'file_not_csv', $body);

        $this->client->request('POST', '/api/v1/imports', ['type' => 'customers'], server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(422, 'No file is a validation error, not a crash.');
    }

    public function testTheListIsNewestFirstAndOnlyOurs(): void
    {
        $this->signInAs('manager');
        $older = $this->upload('customers', self::CSV);
        $newer = $this->upload('leads', self::CSV);

        $list = $this->request('GET', '/api/v1/imports?per_page=2');
        $this->assertStatus(200);
        self::assertSame([$newer['id'], $older['id']], array_column($list['items'], 'id'));
        self::assertGreaterThanOrEqual(2, $list['total']);

        $this->signInAs('other');
        $theirs = $this->request('GET', '/api/v1/imports');
        self::assertNotContains($older['id'], array_column($theirs['items'], 'id'), 'Another dealership sees none of our imports.');
    }

    public function testAnotherDealershipCannotReadAnImport(): void
    {
        $this->signInAs('manager');
        $job = $this->upload('customers', self::CSV);

        $this->assertNotFoundForOtherDealership('GET', '/api/v1/imports/'.$job['id']);
    }

    public function testTheSampleFilesHaveTheDocumentedColumns(): void
    {
        $this->signInAs('manager');
        $customers = $this->sample('customers');
        self::assertStringStartsWith('first_name,last_name,phone,email,address,sales_rep_email,sms_consent,email_consent,consent_source,notes', $customers);
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=customers.csv', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith('first_name,last_name,phone,email,source,vehicle_interest,received_at,sales_rep_email,notes', $this->sample('leads'));

        $this->client->request('GET', '/api/v1/imports/template/vehicles.csv', server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertStatus(404);
    }
}
