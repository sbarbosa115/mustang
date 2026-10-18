<?php

namespace App\Tests\Unit\Access;

use App\Access\Infrastructure\Security\SecurityUser;
use App\Access\UI\Http\Security\MfaRequiredSubscriber;
use App\Access\UI\Http\Security\MfaSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Security audit 2026-10-18, finding 3: the gate let a password-only session through to any path that merely started
 * with "/api/v1/health" (a future "/api/v1/health-details" route would have been open).
 */
final class MfaRequiredSubscriberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'the health route' => ['/api/v1/health', true];
        yield 'the MFA step' => ['/api/v1/auth/mfa/verify', true];
        yield 'a route that starts like health' => ['/api/v1/health-details', false];
        yield 'a business route' => ['/api/v1/customers', false];
    }

    #[DataProvider('paths')]
    public function testAPasswordOnlySessionReachesOnlyTheAuthRoutesAndHealth(string $path, bool $open): void
    {
        $tokens = new TokenStorage();
        $user = new SecurityUser('u1', 'd1', 'sales@mustang.test', 'hash', ['ROLE_SALES'], true);
        $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST);

        new MfaRequiredSubscriber(new MfaSession($tokens, new RequestStack()))($event);

        self::assertSame($open, null === $event->getResponse(), $path);
    }
}
