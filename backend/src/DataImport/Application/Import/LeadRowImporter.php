<?php

namespace App\DataImport\Application\Import;

use App\DataImport\Application\Port\CustomerRecords;
use App\DataImport\Application\Port\LeadIntake;
use App\DataImport\Application\Port\TeamMembers;
use App\DataImport\Domain\Model\ImportJob;
use App\Shared\Application\Port\Clock;

/**
 * One leads.csv row: captures a lead for the customer (matched by phone, then email, or created), handed to the rep
 * named by sales_rep_email, else round robin. A customer who already has an open lead gets no second one, so the
 * same file imported twice opens nothing new. The Deal context opens the deal on LeadCaptured.
 */
final readonly class LeadRowImporter
{
    private const array CAN_TAKE_LEADS = ['sales', 'bdc', 'manager'];

    public function __construct(
        private CustomerMatcher $matcher,
        private CustomerRecords $customers,
        private LeadIntake $leads,
        private TeamMembers $team,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, string> $values
     *
     * @throws \App\Shared\Domain\Error\DomainError the row's refusal; nothing of the row is written before it
     */
    public function import(ImportJob $job, array $values, \DateTimeZone $timezone): RowOutcome
    {
        $row = LeadRow::fromArray($values, $timezone, $this->clock->now());
        $contact = $row->contact;
        $existing = $this->matcher->match($contact);
        $rep = null === $row->salesRepEmail ? null : $this->team->activeMemberByEmail($job->dealershipId(), $row->salesRepEmail);
        $assignee = null !== $rep && \in_array($rep['role'], self::CAN_TAKE_LEADS, true) ? $rep['id'] : null;

        if (null === $existing) {
            $contact->requireNames();
            $this->capture($row, null, $assignee);
            $created = $this->customers->findByContact($contact->phone, null === $contact->phone ? $contact->email : null);
            if (null !== $created) {
                $this->matcher->note($created->id, $job->uploadedByUserId(), $row->notes);
            }

            return RowOutcome::Created;
        }

        $changed = $this->matcher->update($existing, $contact);
        $outcome = $changed ? RowOutcome::Updated : RowOutcome::Skipped;
        if (!$this->leads->hasOpenLead($job->dealershipId(), $existing->id)) {
            $this->capture($row, $existing->id, $assignee);
            $outcome = RowOutcome::Created;
        }
        if ($this->matcher->note($existing->id, $job->uploadedByUserId(), $row->notes) && RowOutcome::Skipped === $outcome) {
            $outcome = RowOutcome::Updated;
        }

        return $outcome;
    }

    private function capture(LeadRow $row, ?string $customerId, ?string $assignee): void
    {
        $contact = $row->contact;
        $this->leads->capture(
            $row->source,
            $customerId,
            null === $customerId ? $contact->firstName : null,
            null === $customerId ? $contact->lastName : null,
            null === $customerId ? $contact->phone : null,
            null === $customerId ? $contact->email : null,
            $row->channelNote,
            $row->vehicleInterest,
            $assignee,
            $row->receivedAt,
        );
    }
}
