<?php

namespace App\Tests\Functional\Compliance;

use App\Messaging\Application\Port\ConsentCheck;
use App\Shared\Domain\Channel;

/**
 * The send check Messaging asks before every message, answered from the ledger and the suppression list.
 */
final class ConsentCheckAdapterTest extends ComplianceTestCase
{
    private const string PHONE = '+18135551003';

    public function testNoRecordIsMissing(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);

        $decision = $this->check($customer);

        self::assertSame('missing', $decision->state);
        self::assertFalse($decision->allowsSending());
        self::assertSame('consent_missing', $decision->refusalCode());
        self::assertNull($decision->consentId);
    }

    public function testAGrantedRecordAllowsTheSendAndNamesTheRecord(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $consent = $this->record($customer, 'text', self::PHONE);

        $decision = $this->check($customer);

        self::assertSame('granted', $decision->state);
        self::assertTrue($decision->allowsSending());
        self::assertSame($consent['id'], $decision->consentId, 'The provenance names the consent record the send relied on.');
    }

    public function testARevokedRecordIsAnOptOut(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $consent = $this->record($customer, 'text', self::PHONE);
        $this->request('POST', '/api/v1/consents/'.$consent['id'].'/revoke', []);

        $decision = $this->check($customer);

        self::assertSame('revoked', $decision->state);
        self::assertSame('opted_out', $decision->refusalCode());
        self::assertSame($consent['id'], $decision->consentId);
    }

    public function testASuppressedContactIsOptedOutWhateverTheLedgerSays(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'text', self::PHONE);
        $this->db()->executeStatement(
            "INSERT INTO suppressions (id, contact_value, channel, reason, created_at, updated_at) VALUES (UUID(), ?, 'text', 'stop_keyword', NOW(), NOW())",
            [self::PHONE],
        );

        $decision = $this->check($customer);

        self::assertSame('opted_out', $decision->state);
        self::assertFalse($decision->allowsSending());
    }

    public function testConsentForAnotherChannelDoesNotCount(): void
    {
        $this->signInAs('sales');
        $customer = $this->customerId(3);
        $this->withACleanLedger($customer);
        $this->record($customer, 'email', 'jessica.brown@example.com');

        self::assertSame('missing', $this->check($customer)->state);
        self::assertSame('granted', $this->check($customer, Channel::Email, 'jessica.brown@example.com')->state);
    }

    private function check(string $customer, Channel $channel = Channel::Text, string $contact = self::PHONE): \App\Messaging\Application\Port\ConsentDecision
    {
        return static::getContainer()->get(ConsentCheck::class)->check($customer, $channel, $contact);
    }
}
