<?php

namespace App\Tests\Functional\Shared;

use App\Tests\Support\ApiTestCase;

/**
 * Item 0 fixes the contract: every route is in the OpenAPI schema with its response type, and a route whose item
 * has not landed answers 501 with the item's name. Items replace the 501 with behaviour.
 */
final class StubShapesTest extends ApiTestCase
{
    public function testTheSchemaDescribesEveryContractRoute(): void
    {
        $schema = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/assets/types/openapi.json'), true, flags: \JSON_THROW_ON_ERROR);

        foreach (['/api/v1/customers/{id}', '/api/v1/leads', '/api/v1/deals/{id}/stage', '/api/v1/conversations/{id}/messages', '/api/v1/worklist/contact-now', '/api/v1/reports/kpis', '/api/v1/imports', '/api/v1/audit'] as $path) {
            self::assertArrayHasKey($path, $schema['paths'], $path.' is part of the contract; regenerate openapi.json.');
        }
        foreach (['CustomerOutput', 'LeadOutput', 'DealOutput', 'MessageOutput', 'ConsentOutput', 'WorklistItemOutput', 'KpiReportOutput', 'AuditEntryOutput'] as $output) {
            self::assertArrayHasKey($output, $schema['components']['schemas']);
        }
    }

    public function testAnUnbuiltRouteNamesItsItem(): void
    {
        $this->signInAs('manager');
        $body = $this->request('GET', '/api/v1/leads');

        if (501 !== $this->client->getResponse()->getStatusCode()) {
            self::markTestSkipped('The leads item has landed.');
        }
        self::assertSame('not_implemented', $body['error']);
        self::assertSame('leads', $body['detail']['owner']);
    }
}
