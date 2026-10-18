<?php

namespace App\Tests\Functional\Audit;

use App\Access\Infrastructure\Security\SecurityTenantContext;
use App\Customer\Domain\Model\Customer;
use App\DataImport\Application\Port\ActingUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * NFR-04: every create, update and delete of a Domain/Model entity is recorded, in the same transaction as the change,
 * with its field diffs (secrets masked), who did it, the dealership and the request.
 */
final class ChangeRecorderTest extends AuditTestCase
{
    public function testAnUpdateThroughTheApiRecordsOneEntryWithTheFieldDiffTheActorAndTheRequest(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(1);
        $before = (string) $this->db()->fetchOne('SELECT first_name FROM customers WHERE id = ?', [$id]);

        $this->requestWithId('PATCH', '/api/v1/customers/'.$id, ['first_name' => 'Renamed'], 'req-aud-update-1');
        $this->assertStatus(200);

        $entries = $this->entries('Customer', $id, 'update');
        self::assertCount(1, $entries, 'One change of one customer is one entry.');
        $entry = $entries[0];
        self::assertSame(['old' => $before, 'new' => 'Renamed'], $entry['changes']['first_name'] ?? null, 'The diff names the column with its old and new value.');
        self::assertArrayNotHasKey('updated_at', $entry['changes'] ?? [], 'Bookkeeping columns are not a change worth reading.');
        self::assertArrayNotHasKey('last_name', $entry['changes'] ?? [], 'Only what changed is in the diff.');
        self::assertSame($this->userId('sales'), $entry['actor_user_id'], 'The signed-in user is the actor.');
        self::assertSame($this->dealershipId(), $entry['dealership_id'], 'The dealership is the changed record\'s.');
        self::assertSame($id, $entry['customer_id'], 'A customer\'s own entry points at the customer.');
        self::assertSame('req-aud-update-1', $entry['request_id'], 'The caller\'s X-Request-Id ties the entry to the request.');
        self::assertNotNull($entry['ip']);
    }

    public function testACreateRecordsOneEntryPerNewEntityEachPointingAtTheCustomer(): void
    {
        $this->signInAs('bdc');

        $body = $this->request('POST', '/api/v1/customers', ['first_name' => 'Audit', 'last_name' => 'Create', 'phone' => '+1 813 555 0177']);
        $this->assertStatus(201);
        $id = (string) $body['id'];

        $customer = $this->entries('Customer', $id, 'create');
        self::assertCount(1, $customer);
        self::assertSame(['old' => null, 'new' => 'Audit'], $customer[0]['changes']['first_name'] ?? null);
        self::assertArrayNotHasKey('id', $customer[0]['changes'] ?? [], 'The id is the entry\'s entity_id, not a change.');

        $contactId = (string) $this->db()->fetchOne('SELECT id FROM customer_contacts WHERE customer_id = ?', [$id]);
        $contact = $this->entries('CustomerContact', $contactId, 'create');
        self::assertCount(1, $contact, 'The new contact is a change of its own.');
        self::assertSame($id, $contact[0]['customer_id'], 'A contact\'s entry points at its customer, so a customer\'s history includes it.');
        self::assertSame(['old' => null, 'new' => '+18135550177'], $contact[0]['changes']['value'] ?? null);
        self::assertSame(['old' => null, 'new' => $id], $contact[0]['changes']['customer_id'] ?? null, 'An association is recorded by the id it points at.');
        self::assertSame(['old' => null, 'new' => 'phone'], $contact[0]['changes']['kind'] ?? null, 'An enum is recorded by its value.');
    }

    public function testADeleteRecordsTheValuesThatWereRemoved(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(2);
        $added = $this->request('POST', '/api/v1/customers/'.$id.'/contacts', ['kind' => 'email', 'value' => 'to.remove@example.com']);
        $this->assertStatus(201);
        $contactId = (string) $this->db()->fetchOne("SELECT id FROM customer_contacts WHERE customer_id = ? AND value = 'to.remove@example.com'", [$id]);
        unset($added);

        $this->request('DELETE', '/api/v1/customers/'.$id.'/contacts/'.$contactId);
        $this->assertStatus(200);

        $entries = $this->entries('CustomerContact', $contactId, 'delete');
        self::assertCount(1, $entries);
        self::assertSame(['old' => 'to.remove@example.com', 'new' => null], $entries[0]['changes']['value'] ?? null, 'A delete keeps what was removed.');
        self::assertSame($id, $entries[0]['customer_id']);
    }

    public function testPasswordHashesMfaSecretsAndRecoveryCodesAreMasked(): void
    {
        $this->signInAs('admin');
        $body = $this->request('POST', '/api/v1/users', [
            'email' => 'audit.masked@mustang.test', 'first_name' => 'Mask', 'last_name' => 'Ed', 'role' => 'sales', 'password' => 'a-long-enough-password',
        ]);
        $this->assertStatus(201);
        $userId = (string) $body['id'];
        $hash = (string) $this->db()->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        $user = $this->entries('User', $userId, 'create');
        self::assertCount(1, $user);
        self::assertSame(['old' => null, 'new' => '[masked]'], $user[0]['changes']['password_hash'] ?? null, 'That the password was set is recorded, never its hash.');
        self::assertSame(['old' => null, 'new' => 'audit.masked@mustang.test'], $user[0]['changes']['email'] ?? null);

        $target = $this->userId('bdc');
        $credential = $this->db()->fetchAssociative('SELECT id, secret_encrypted FROM mfa_credentials WHERE user_id = ?', [$target]);
        self::assertIsArray($credential, 'Seed users have two-step sign-in set up.');
        $this->request('DELETE', '/api/v1/users/'.$target.'/mfa');
        $this->assertStatus(204);

        $deleted = $this->entries('MfaCredential', (string) $credential['id'], 'delete');
        self::assertCount(1, $deleted);
        self::assertSame('[masked]', $deleted[0]['changes']['secret_encrypted']['old'] ?? null, 'The MFA secret is never copied into the log.');
        self::assertSame('[masked]', $deleted[0]['changes']['recovery_codes_json']['old'] ?? null, 'Nor are the recovery codes.');

        $raw = (string) $this->db()->fetchOne('SELECT GROUP_CONCAT(changes_json) FROM audit_entries');
        self::assertStringNotContainsString($hash, $raw);
        self::assertStringNotContainsString((string) $credential['secret_encrypted'], $raw);
    }

    public function testAuditEntriesAreNeverRecordedThemselves(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(3);
        $this->request('GET', '/api/v1/customers/'.$id);
        $this->request('PATCH', '/api/v1/customers/'.$id, ['first_name' => 'Twice']);
        $this->assertStatus(200);

        self::assertGreaterThan(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM audit_entries'));
        self::assertSame(0, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM audit_entries WHERE entity_type = 'AuditEntry'"), 'Appending to the log is not a change of the log.');
    }

    public function testARolledBackChangeLeavesNoEntryBecauseBothShareOneTransaction(): void
    {
        $this->signInAs('sales');
        $this->request('GET', '/api/v1/me');
        $id = $this->customerId(4);
        $before = (string) $this->db()->fetchOne('SELECT first_name FROM customers WHERE id = ?', [$id]);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        try {
            $em->wrapInTransaction(static function () use ($em, $id): void {
                $customer = $em->find(Customer::class, $id);
                self::assertInstanceOf(Customer::class, $customer);
                $customer->rename('Rolled', 'Back');
                $em->flush();
                throw new \RuntimeException('the command fails after its flush');
            });
            self::fail('The transaction must throw.');
        } catch (\RuntimeException $e) {
            self::assertSame('the command fails after its flush', $e->getMessage());
        }

        self::assertSame($before, $this->db()->fetchOne('SELECT first_name FROM customers WHERE id = ?', [$id]), 'The change was rolled back.');
        self::assertSame([], $this->entries('Customer', $id), 'So was its audit entry: a change and its entry commit or roll back together.');
    }

    public function testAConsoleCommandRecordsItsActingUserAndNobodyIsANullActor(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tenant = $container->get(SecurityTenantContext::class);
        $id = $this->customerId(5);

        $tenant->actAs($this->dealershipId(), $this->userId('manager'));
        $customer = $em->find(Customer::class, $id);
        self::assertInstanceOf(Customer::class, $customer);
        $customer->rename('Console', 'Change');
        $em->flush();
        $tenant->reset();

        $customer->rename('Nobody', 'Signed');
        $em->flush();

        $entries = $this->entries('Customer', $id, 'update');
        self::assertCount(2, $entries);
        self::assertSame($this->userId('manager'), $entries[0]['actor_user_id'], 'A command acting as a user records that user.');
        self::assertNull($entries[1]['actor_user_id'], 'Work with nobody signed in (a reminder) has no actor.');
        self::assertSame($this->dealershipId(), $entries[1]['dealership_id'], 'The dealership comes from the record, not from a sign-in.');
        self::assertNull($entries[1]['request_id'], 'Outside a request there is no request id.');
    }

    public function testWorkOnTheWorkerRecordsTheUserItRunsAs(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $acting = $container->get(ActingUser::class);
        $id = $this->customerId(6);

        $acting->runAs($this->dealershipId(), $this->userId('admin'), static function () use ($em, $id): void {
            $customer = $em->find(Customer::class, $id);
            self::assertInstanceOf(Customer::class, $customer);
            $customer->rename('Imported', 'Name');
            $em->flush();
        });

        $entries = $this->entries('Customer', $id, 'update');
        self::assertCount(1, $entries);
        self::assertSame($this->userId('admin'), $entries[0]['actor_user_id'], 'An import runs as its uploader, who is the actor.');
    }

    public function testEachRequestWithoutAnIdGetsItsOwnAndAnswersWithIt(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(7);

        $this->request('PATCH', '/api/v1/customers/'.$id, ['first_name' => 'First']);
        $first = $this->client->getResponse()->headers->get('X-Request-Id');
        $this->request('PATCH', '/api/v1/customers/'.$id, ['first_name' => 'Second']);
        $second = $this->client->getResponse()->headers->get('X-Request-Id');

        $entries = $this->entries('Customer', $id, 'update');
        self::assertCount(2, $entries);
        self::assertNotNull($first);
        self::assertNotSame($first, $second, 'Every request gets an id of its own.');
        self::assertSame([$first, $second], array_column($entries, 'request_id'), 'The response names the id its entries carry.');
    }

    public function testARequestIdThatIsNotATokenIsReplaced(): void
    {
        $this->signInAs('sales');
        $id = $this->customerId(8);

        $this->requestWithId('PATCH', '/api/v1/customers/'.$id, ['first_name' => 'Bad'], str_repeat('x', 200));
        $this->assertStatus(200);

        $entry = $this->entries('Customer', $id, 'update')[0];
        self::assertNotNull($entry['request_id']);
        self::assertLessThanOrEqual(64, \strlen($entry['request_id']), 'A header too long for the column is replaced, never truncated into a collision.');
    }

    public function testSeedingTheDemoDataIsNotRecorded(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $tenant = $container->get(SecurityTenantContext::class);
        $command = new Command('mustang:seed-demo');
        $input = new ArrayInput([]);
        $output = new NullOutput();
        $id = $this->customerId(9);
        $customer = $em->find(Customer::class, $id);
        self::assertInstanceOf(Customer::class, $customer);
        $tenant->actAs($this->dealershipId());

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), 'console.command');
        $customer->rename('Seeded', 'Data');
        $em->flush();
        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 0), 'console.terminate');

        self::assertSame([], $this->entries('Customer', $id), 'Demo data is made up, not something a person did.');

        $customer->rename('After', 'Seeding');
        $em->flush();
        self::assertCount(1, $this->entries('Customer', $id), 'Recording resumes when the seeding ends.');
    }
}
