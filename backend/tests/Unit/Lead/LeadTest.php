<?php

namespace App\Tests\Unit\Lead;

use App\Lead\Domain\Model\Lead;
use App\Lead\Domain\Model\LeadSource;
use App\Lead\Domain\Model\LeadStatus;
use App\Lead\Domain\Model\RoutingRotation;
use App\Shared\Domain\Error\Conflict;
use App\Shared\Domain\Error\InvalidValue;
use PHPUnit\Framework\TestCase;

final class LeadTest extends TestCase
{
    private function lead(?string $assignee = null): Lead
    {
        return Lead::capture('d1', 'c1', LeadSource::Phone, new \DateTimeImmutable('2026-10-01 10:00'), $assignee);
    }

    public function testACapturedLeadIsAssignedWhenItHasARepAndNewOtherwise(): void
    {
        self::assertSame(LeadStatus::Assigned, $this->lead('u1')->status());
        self::assertSame(LeadStatus::New, $this->lead()->status(), 'Nobody to route to: the lead waits as new.');
    }

    public function testAssigningReportsWhetherTheAssigneeChanged(): void
    {
        $lead = $this->lead();
        self::assertTrue($lead->assignTo('u1'));
        self::assertSame(LeadStatus::Assigned, $lead->status());
        self::assertFalse($lead->assignTo('u1'), 'The same rep again is not a change.');
        self::assertSame('u1', $lead->assignedUserId());
    }

    public function testAssigningKeepsAContactedLeadContacted(): void
    {
        $lead = new Lead('d1', 'c1', LeadSource::Phone, new \DateTimeImmutable(), LeadStatus::Contacted, assignedUserId: 'u1');
        $lead->assignTo('u2');
        self::assertSame(LeadStatus::Contacted, $lead->status());
    }

    public function testAClosedLeadCannotBeClosedOrReassigned(): void
    {
        $lead = $this->lead('u1');
        $lead->close();
        self::assertSame(LeadStatus::Closed, $lead->status());

        try {
            $lead->close();
            self::fail('Closing twice must be refused.');
        } catch (Conflict $e) {
            self::assertSame('lead_closed', $e->errorCode());
        }
        $this->expectException(Conflict::class);
        $lead->assignTo('u2');
    }

    public function testARotationRefusesTheSameRepTwice(): void
    {
        $rotation = new RoutingRotation('d1', ['a', 'b'], 1);
        $this->expectException(InvalidValue::class);
        $rotation->reorder(['a', 'b', 'a']);
    }

    public function testReorderingStartsTheRotationOver(): void
    {
        $rotation = new RoutingRotation('d1', ['a', 'b'], 1);
        $rotation->reorder(['b', 'c']);
        self::assertSame(['b', 'c'], $rotation->userIds());
        self::assertSame('b', $rotation->peek(['b', 'c']));
    }
}
