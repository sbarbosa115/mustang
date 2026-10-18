<?php

namespace App\Worklist\UI\Http;

use App\Shared\UI\Http\Output\ChannelConsentOutput;
use App\Shared\UI\Http\Output\CustomerRefOutput;
use App\Shared\UI\Http\Output\UserRefOutput;
use App\Worklist\Domain\Model\AppointmentFacts;
use App\Worklist\Domain\Model\CustomerFacts;
use App\Worklist\Domain\Model\DealFacts;
use App\Worklist\Domain\Model\Intervention;
use App\Worklist\Domain\Model\Now;
use App\Worklist\Domain\Model\Priority;
use App\Worklist\Domain\Rule\NoShowWithoutFollowUp;
use App\Worklist\UI\Http\Output\InterventionOutput;
use App\Worklist\UI\Http\Output\WorklistAppointmentOutput;
use App\Worklist\UI\Http\Output\WorklistDealOutput;
use App\Worklist\UI\Http\Output\WorklistItemOutput;

final readonly class WorklistPresenter
{
    public function item(Priority $row, Now $now): WorklistItemOutput
    {
        $customer = $row->customer;
        // The appointment the reason is about: the missed one for a no-show, else today's.
        $appointment = NoShowWithoutFollowUp::CODE === $row->reason->code ? $customer->lastNoShow : $customer->appointmentToday;

        return new WorklistItemOutput(
            customer: self::customer($customer),
            reasonCode: $row->reason->code,
            reasonParams: $row->reason->params,
            score: $row->reason->score,
            hot: $row->reason->hot,
            channelAvailability: new ChannelConsentOutput($customer->consent['text'], $customer->consent['email'], $customer->consent['call']),
            suggestedChannel: $row->suggestedChannel,
            deal: null === $customer->deal ? null : self::deal($customer->deal, $now),
            appointment: null === $appointment ? null : self::appointment($appointment),
            since: $row->reason->since,
            phone: $customer->phone,
        );
    }

    /**
     * @param array<string, string> $names user id => name
     */
    public function intervention(Intervention $item, Now $now, array $names): InterventionOutput
    {
        $assignee = $item->deal->assignedUserId;

        return new InterventionOutput(
            deal: self::deal($item->deal, $now),
            customer: self::customer($item->customer),
            reasonCode: $item->reason->code,
            reasonParams: $item->reason->params,
            atRiskCents: $item->atRiskCents,
            assignee: null === $assignee || !isset($names[$assignee]) ? null : new UserRefOutput($assignee, $names[$assignee]),
            since: $item->reason->since,
        );
    }

    private static function customer(CustomerFacts $customer): CustomerRefOutput
    {
        return new CustomerRefOutput($customer->customerId, $customer->customerName);
    }

    private static function deal(DealFacts $deal, Now $now): WorklistDealOutput
    {
        return new WorklistDealOutput($deal->id, $deal->stage, $deal->daysInStage($now), $deal->vehicleText);
    }

    private static function appointment(AppointmentFacts $appointment): WorklistAppointmentOutput
    {
        return new WorklistAppointmentOutput($appointment->id, $appointment->scheduledAt, $appointment->status);
    }
}
