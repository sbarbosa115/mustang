<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Whether this session has proved the second factor. The mark lives on the security token, which the firewall keeps
 * in the session: a new sign-in is a new token, so it starts unverified, and signing out drops it.
 */
final readonly class MfaSession
{
    public const string VERIFIED_ATTRIBUTE = 'mfa_verified';

    public function __construct(private TokenStorageInterface $tokens, private RequestStack $requests)
    {
    }

    public function isSignedIn(): bool
    {
        return null !== $this->tokens->getToken()?->getUser();
    }

    public function isVerified(): bool
    {
        $token = $this->tokens->getToken();

        return null !== $token && $token->hasAttribute(self::VERIFIED_ATTRIBUTE) && true === $token->getAttribute(self::VERIFIED_ATTRIBUTE);
    }

    public function markVerified(): void
    {
        $token = $this->tokens->getToken() ?? throw new \LogicException('Only a signed-in session can be verified.');
        $token->setAttribute(self::VERIFIED_ATTRIBUTE, true);
        // The session gains privileges: give it a new id, as the sign-in itself did.
        $this->requests->getSession()->migrate(true);
    }
}
