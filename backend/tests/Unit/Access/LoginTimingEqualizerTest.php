<?php

namespace App\Tests\Unit\Access;

use App\Access\UI\Http\Security\LoginTimingEqualizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Security audit 2026-10-18, finding 1: an unknown email answered in 27 ms, a known one in 440 ms (the password hash),
 * so the timing told which accounts exist although the message is the same.
 */
final class LoginTimingEqualizerTest extends TestCase
{
    public function testAnUnknownEmailCostsAPasswordHashLikeAKnownOne(): void
    {
        $hasher = $this->spy();
        $this->failSignIn(new BadCredentialsException('Bad credentials.', 0, new UserNotFoundException()), $hasher);

        self::assertSame(1, $hasher->hashes, 'The failure must spend the time a password check spends.');
    }

    public function testADeactivatedAccountCostsAPasswordHashToo(): void
    {
        $hasher = $this->spy();
        $this->failSignIn(new BadCredentialsException('Bad credentials.', 0, new CustomUserMessageAccountStatusException('account_disabled')), $hasher);

        self::assertSame(1, $hasher->hashes, 'The account checker refuses before the password is checked.');
    }

    public function testAWrongPasswordIsNotHashedTwice(): void
    {
        $hasher = $this->spy();
        $this->failSignIn(new BadCredentialsException('The presented password is invalid.'), $hasher);

        self::assertSame(0, $hasher->hashes);
    }

    private function failSignIn(AuthenticationException $exception, PasswordHasherInterface $hasher): void
    {
        $factory = $this->createStub(PasswordHasherFactoryInterface::class);
        $factory->method('getPasswordHasher')->willReturn($hasher);
        $request = Request::create('/api/v1/auth/login', 'POST', content: '{"email":"nobody@mustang.test","password":"guess"}');

        new LoginTimingEqualizer($factory)(new LoginFailureEvent($exception, $this->createStub(AuthenticatorInterface::class), $request, null, 'main'));
    }

    private function spy(): CountingHasher
    {
        return new CountingHasher();
    }
}

/**
 * Counts the password hashes a failure spends.
 */
final class CountingHasher implements PasswordHasherInterface
{
    public int $hashes = 0;

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        ++$this->hashes;

        return 'hashed';
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
    {
        return false;
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return false;
    }
}
