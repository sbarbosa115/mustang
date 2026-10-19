<?php

namespace App\Tests\Unit\Lead;

use App\Lead\Application\Port\SalesTeam;
use App\Lead\Domain\Model\RoutingRotation;
use App\Lead\Domain\Repository\RoutingRotationRepository;
use App\Lead\Infrastructure\Routing\RoundRobinRouter;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\NotFound;
use PHPUnit\Framework\TestCase;

final class RoundRobinRouterTest extends TestCase
{
    private InMemoryRotations $rotations;
    private FakeSalesTeam $team;
    private RoundRobinRouter $router;

    protected function setUp(): void
    {
        $this->rotations = new InMemoryRotations();
        $this->team = new FakeSalesTeam(['a', 'b', 'c']);
        $this->router = new RoundRobinRouter($this->rotations, $this->team, new FixedTenant());
    }

    public function testRepsAreTakenInTheRotationOrder(): void
    {
        $this->rotations->stored = new RoutingRotation('d1', ['c', 'a', 'b']);

        self::assertSame(['c', 'a', 'b'], [$this->router->route(), $this->router->route(), $this->router->route()], 'Leads go to the reps in the order the manager set.');
    }

    public function testTheRotationWrapsAroundToTheFirstRep(): void
    {
        $this->rotations->stored = new RoutingRotation('d1', ['a', 'b']);
        $this->team->active = ['a', 'b'];

        self::assertSame(['a', 'b', 'a', 'b', 'a'], array_map(fn (): ?string => $this->router->route(), range(1, 5)));
    }

    public function testAnInactiveRepIsSkippedAndKeepsNoTurn(): void
    {
        $this->rotations->stored = new RoutingRotation('d1', ['a', 'b', 'c']);
        $this->team->active = ['a', 'c'];

        self::assertSame(['a', 'c', 'a', 'c'], array_map(fn (): ?string => $this->router->route(), range(1, 4)), 'A deactivated rep (b) gets no leads.');
    }

    public function testAnExplicitAssigneeBypassesTheRotationAndDoesNotUseATurn(): void
    {
        $this->rotations->stored = new RoutingRotation('d1', ['a', 'b', 'c']);

        self::assertSame('c', $this->router->route('c'));
        self::assertSame('a', $this->router->route(), 'The next routed lead still goes to the first rep.');
    }

    public function testWithoutARotationTheActiveRepsAreUsedAndTheRotationIsCreated(): void
    {
        self::assertSame('a', $this->router->route());
        self::assertNotNull($this->rotations->stored, 'The rotation row is created on first use.');
        self::assertSame(['a', 'b', 'c'], $this->rotations->stored->userIds());
        self::assertSame('b', $this->router->route());
    }

    public function testNobodyIsAssignedWhenNoRepIsEligible(): void
    {
        $this->rotations->stored = new RoutingRotation('d1', ['a', 'b']);
        $this->team->active = [];

        self::assertNull($this->router->route(), 'With nobody to route to, the lead stays unassigned.');
    }

    public function testThePreviewNamesTheNextRepWithoutTakingATurn(): void
    {
        $this->rotations->stored = new RoutingRotation('d1', ['a', 'b', 'c'], 1);
        $this->team->active = ['a', 'c'];

        self::assertSame(['user_ids' => ['a', 'c'], 'next' => 'c'], $this->router->preview(), 'An inactive rep is left out of the order shown.');
        self::assertSame('c', $this->router->route(), 'Previewing did not move the rotation.');
    }
}

final class InMemoryRotations implements RoutingRotationRepository
{
    public ?RoutingRotation $stored = null;

    public function get(string $id): RoutingRotation
    {
        return $this->stored ?? throw new NotFound('routing_not_found', 'none');
    }

    public function add(RoutingRotation $routingRotation): void
    {
        $this->stored = $routingRotation;
    }

    public function findCurrent(bool $forUpdate = false): ?RoutingRotation
    {
        return $this->stored;
    }
}

final class FakeSalesTeam implements SalesTeam
{
    /** @param list<string> $active */
    public function __construct(public array $active)
    {
    }

    public function activeSalesIds(): array
    {
        return $this->active;
    }

    public function activeRoleOf(string $userId): ?string
    {
        return \in_array($userId, $this->active, true) ? 'sales' : null;
    }
}

final class FixedTenant implements TenantContext
{
    public function dealershipId(): string
    {
        return 'd1';
    }

    public function userId(): string
    {
        return 'u1';
    }

    public function hasRole(string $role): bool
    {
        return true;
    }
}
