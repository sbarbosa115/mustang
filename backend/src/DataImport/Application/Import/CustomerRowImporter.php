<?php

namespace App\DataImport\Application\Import;

use App\DataImport\Application\Port\ConsentLedger;
use App\DataImport\Application\Port\CustomerRecords;
use App\DataImport\Application\Port\TeamMembers;
use App\DataImport\Domain\Model\ImportJob;
use App\Shared\Domain\Channel;

/**
 * One customers.csv row: creates the customer or updates the duplicate, puts the rep in their slot (sales or manager
 * as Sales 1, BDC agent as BDC; a rep email nobody has leaves the slot as it is), records consent for the channels
 * marked yes, and adds the notes.
 */
final readonly class CustomerRowImporter
{
    public const string CONSENT_SOURCE = 'import';
    public const string CONSENT_WORDING = 'Imported from the dealer\'s previous CRM';

    public function __construct(
        private CustomerMatcher $matcher,
        private CustomerRecords $customers,
        private ConsentLedger $consents,
        private TeamMembers $team,
    ) {
    }

    /**
     * @param array<string, string> $values
     *
     * @throws \App\Shared\Domain\Error\DomainError the row's refusal; nothing of the row is written before it
     */
    public function import(ImportJob $job, array $values): RowOutcome
    {
        $row = CustomerRow::fromArray($values);
        $contact = $row->contact;
        $existing = $this->matcher->match($contact);
        $rep = null === $row->salesRepEmail ? null : $this->team->activeMemberByEmail($job->dealershipId(), $row->salesRepEmail);

        if (null === $existing) {
            $contact->requireNames();
            $sales1 = null !== $rep && \in_array($rep['role'], ['sales', 'manager'], true) ? $rep['id'] : null;
            $customerId = $this->customers->create((string) $contact->firstName, (string) $contact->lastName, $contact->phone, $contact->email, $sales1, 'import');
            if (null !== $rep && 'bdc' === $rep['role']) {
                $this->customers->assignTeam($customerId, null, null, $rep['id']);
            }
            $this->consentAndNotes($job, $row, $customerId);

            return RowOutcome::Created;
        }

        $changed = $this->matcher->update($existing, $contact);
        if (null !== $rep) {
            $team = [$existing->sales1UserId, $existing->sales2UserId, $existing->bdcUserId];
            $wanted = match ($rep['role']) {
                'sales', 'manager' => [$rep['id'], $existing->sales2UserId === $rep['id'] ? null : $existing->sales2UserId, $existing->bdcUserId],
                'bdc' => [$existing->sales1UserId, $existing->sales2UserId, $rep['id']],
                default => $team,
            };
            if ($wanted !== $team) {
                $this->customers->assignTeam($existing->id, ...$wanted);
                $changed = true;
            }
        }
        $changed = $this->consentAndNotes($job, $row, $existing->id) || $changed;

        return $changed ? RowOutcome::Updated : RowOutcome::Skipped;
    }

    private function consentAndNotes(ImportJob $job, CustomerRow $row, string $customerId): bool
    {
        $changed = false;
        foreach ([[Channel::Text, $row->smsConsent, $row->contact->phone], [Channel::Email, $row->emailConsent, $row->contact->email]] as [$channel, $yes, $value]) {
            // Only where the ledger has nothing: a spreadsheet never overrides a revocation or a STOP.
            if ($yes && null !== $value && $this->consents->isMissing($customerId, $channel, $value)) {
                $this->consents->record($job->dealershipId(), $customerId, $channel, $value, self::CONSENT_SOURCE, self::CONSENT_WORDING, $row->consentSource, $job->uploadedByUserId());
                $changed = true;
            }
        }

        return $this->matcher->note($customerId, $job->uploadedByUserId(), $row->noteBody()) || $changed;
    }
}
