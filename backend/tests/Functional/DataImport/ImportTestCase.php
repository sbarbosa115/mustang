<?php

namespace App\Tests\Functional\DataImport;

use App\DataImport\Application\Command\RunImport;
use App\DataImport\Application\EventHandler\RunImportHandler;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Uploads a CSV through the API and runs what the worker would, the way the worker does: with nobody signed in.
 */
abstract class ImportTestCase extends ApiTestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }
        foreach (glob(\dirname(__DIR__, 3).'/var/uploads/imports/*.csv') ?: [] as $stored) {
            @unlink($stored);
        }
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    protected function upload(string $type, string $contents, string $name = 'import.csv'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, $contents);
        $this->temp[] = $path;
        $this->client->request(
            'POST',
            '/api/v1/imports',
            ['type' => $type],
            ['file' => new UploadedFile($path, $name, 'text/csv', null, true)],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    protected function sample(string $type): string
    {
        $this->client->request('GET', '/api/v1/imports/template/'.$type.'.csv');
        $this->assertStatus(200, 'The sample file is served by the API.');

        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * Runs every queued RunImport as the worker does: no signed-in user, so the import must carry its dealership.
     */
    protected function runWorker(): void
    {
        /** @var TokenStorageInterface $tokens */
        $tokens = static::getContainer()->get('security.token_storage');
        $tokens->setToken(null);
        // The traced buses record only while the profiler is on, which it is not between requests.
        static::getContainer()->get('profiler')->enable();
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof RunImport) {
                static::getContainer()->get(RunImportHandler::class)($message);
            }
        }
        $transport->reset();
        self::assertNull($tokens->getToken(), 'The worker acts as the uploader only while it processes the file.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function importAndRun(string $type, string $contents, string $as = 'manager'): array
    {
        $this->signInAs($as);
        $job = $this->upload($type, $contents);
        $this->assertStatus(201);
        $this->runWorker();
        $this->signInAs($as);

        return $this->request('GET', '/api/v1/imports/'.$job['id']);
    }

    protected function customerIdByPhone(string $e164): string|false
    {
        return $this->db()->fetchOne(
            "SELECT customer_id FROM customer_contacts WHERE kind = 'phone' AND value = ? AND dealership_id = ?",
            [$e164, $this->dealershipId()],
        );
    }

    protected function customerIdByEmail(string $email): string|false
    {
        return $this->db()->fetchOne(
            "SELECT customer_id FROM customer_contacts WHERE kind = 'email' AND value = ? AND dealership_id = ?",
            [$email, $this->dealershipId()],
        );
    }
}
