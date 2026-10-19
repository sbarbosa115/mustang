<?php

namespace App\Tests\Functional\Compliance;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class StopKeywordTest extends ComplianceTestCase
{
    private const string PHONE = '+18135551003';

    /**
     * @return iterable<string, array{string}>
     */
    public static function stopWords(): iterable
    {
        foreach (['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'stop', ' Quit '] as $word) {
            yield $word => [$word];
        }
    }

    #[DataProvider('stopWords')]
    public function testAStopWordRevokesEveryChannelOfThePhoneAndSuppressesIt(string $word): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'text', self::PHONE);
        $this->record($customer, 'call', self::PHONE);
        $this->record($customer, 'email', 'jessica.brown@example.com');

        $this->inbound(self::PHONE, $word);

        $this->assertStatus(201);
        $ledger = $this->ledger($customer);
        self::assertSame(['text' => 'opted_out', 'email' => 'granted', 'call' => 'revoked'], $ledger['summary'], 'STOP ends text and call for that phone; email is another contact.');
        self::assertTrue($this->isSuppressed(self::PHONE), 'The phone goes on the global suppression list.');
        $text = array_values(array_filter($ledger['items'], static fn (array $c): bool => 'text' === $c['channel']));
        self::assertSame('revoked', $text[0]['state']);
        self::assertSame('stop_keyword', $text[0]['revoked_reason']);
    }

    public function testAStopWithoutAnyConsentStillLeavesARecordAndASuppression(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);

        $this->inbound(self::PHONE, 'STOP');

        $ledger = $this->ledger($customer);
        self::assertSame('opted_out', $ledger['summary']['text']);
        self::assertCount(1, $ledger['items'], 'The opt-out is written in the ledger even with nothing to revoke.');
        self::assertSame(['text', 'revoked', 'sms_keyword', 'STOP'], [$ledger['items'][0]['channel'], $ledger['items'][0]['state'], $ledger['items'][0]['source'], $ledger['items'][0]['wording']]);
    }

    public function testASentenceWithStopInItIsNotAKeyword(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'text', self::PHONE);

        $this->inbound(self::PHONE, 'Can you stop by the trade-in desk?');

        self::assertSame('granted', $this->ledger($customer)['summary']['text']);
        self::assertFalse($this->isSuppressed(self::PHONE));
    }

    public function testAStopByEmailIsNotAKeyword(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'email', 'jessica.brown@example.com');

        $this->inbound('jessica.brown@example.com', 'STOP', 'email');

        self::assertSame('granted', $this->ledger($customer)['summary']['email'], 'Keywords are a text-message convention.');
    }

    public function testTheSuppressionBlocksEveryDealership(): void
    {
        $this->signInAs('other');
        $otherCustomer = $this->otherCustomerId(1);
        $this->request('POST', '/api/v1/customers/'.$otherCustomer.'/contacts', ['kind' => 'phone', 'value' => self::PHONE, 'is_primary' => true]);
        $this->assertStatus(201, 'Another dealership has a customer with the same phone.');
        $this->withACleanLedger($otherCustomer);
        $this->record($otherCustomer, 'text', self::PHONE);
        self::assertSame('granted', $this->ledger($otherCustomer)['summary']['text']);

        $this->signInAs('sales');
        $this->withACleanLedger($this->customerId(3));
        $this->inbound(self::PHONE, 'STOP');

        $this->signInAs('other');
        self::assertSame('opted_out', $this->ledger($otherCustomer)['summary']['text'], 'A STOP at one dealership blocks the phone at every dealership.');
        $suppressions = $this->request('GET', '/api/v1/compliance/suppressions');
        self::assertContains(self::PHONE, array_column($suppressions['items'], 'contact_value'));
    }

    public function testStartLiftsTheSuppressionAndRecordsConsentFromTheKeyword(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'text', self::PHONE);
        $this->inbound(self::PHONE, 'STOP');

        $this->inbound(self::PHONE, 'Start');

        self::assertFalse($this->isSuppressed(self::PHONE), 'START removes the phone from the suppression list.');
        $ledger = $this->ledger($customer);
        self::assertSame('granted', $ledger['summary']['text']);
        self::assertSame(['granted', 'sms_keyword'], [$ledger['items'][0]['state'], $ledger['items'][0]['source']], 'START is recorded as consent with source sms_keyword.');
        self::assertNull($ledger['items'][0]['captured_by'], 'Nobody on staff captured it: the customer texted it.');
        self::assertSame('missing', $ledger['summary']['call'], 'START is about texts; it records no call consent.');
    }

    public function testUnstopWorksLikeStart(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->inbound(self::PHONE, 'STOP');

        $this->inbound(self::PHONE, 'UNSTOP');

        self::assertSame('granted', $this->ledger($customer)['summary']['text']);
    }

    public function testAKeywordFromTheConsoleWorksWithoutASignedInUser(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'text', self::PHONE);

        $kernel = self::$kernel ?? throw new \LogicException('The kernel is booted by the client.');
        static::getContainer()->get('security.token_storage')->setToken(null);
        $tester = new CommandTester((new Application($kernel))->find('mustang:messaging:simulate-inbound'));
        $tester->execute(['from' => self::PHONE, 'body' => 'STOP', '--dealership' => $this->dealershipId()]);

        $tester->assertCommandIsSuccessful('The worker and the console have no signed-in user; the dealership comes from the message.');
        self::assertTrue($this->isSuppressed(self::PHONE));
        self::assertSame('opted_out', $this->ledger($customer)['summary']['text']);
    }
}
