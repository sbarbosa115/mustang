<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * An unknown email or a deactivated account is refused before any password is checked, so it answered in a fraction
 * of the time a wrong password takes and the timing told which accounts exist. Spending one password hash on those
 * failures makes every refusal take about as long.
 */
#[AsEventListener(event: LoginFailureEvent::class)]
final readonly class LoginTimingEqualizer
{
    public function __construct(private PasswordHasherFactoryInterface $hashers)
    {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        if (!$this->passwordWasNotChecked($event->getException())) {
            return;
        }
        $body = json_decode((string) $event->getRequest()->getContent(), true);
        $password = \is_array($body) && \is_string($body['password'] ?? null) ? $body['password'] : '';

        $this->hashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->hash(mb_substr($password, 0, 200));
    }

    private function passwordWasNotChecked(\Throwable $exception): bool
    {
        for ($cause = $exception; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof UserNotFoundException || $cause instanceof AccountStatusException) {
                return true;
            }
        }

        return false;
    }
}
