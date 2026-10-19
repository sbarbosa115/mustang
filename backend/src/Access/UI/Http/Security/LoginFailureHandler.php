<?php

namespace App\Access\UI\Http\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Wrong email and wrong password get the same answer, so the form never tells which accounts exist.
 */
final class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        [$status, $code, $message] = match (true) {
            $exception instanceof TooManyLoginAttemptsAuthenticationException => [Response::HTTP_TOO_MANY_REQUESTS, 'too_many_attempts', 'Too many attempts. Wait a minute and try again.'],
            $exception instanceof CustomUserMessageAccountStatusException => [Response::HTTP_UNAUTHORIZED, 'invalid_credentials', 'The email or password is not right.'],
            default => [Response::HTTP_UNAUTHORIZED, 'invalid_credentials', 'The email or password is not right.'],
        };

        return new JsonResponse(['error' => $code, 'message' => $message, 'detail' => (object) []], $status);
    }
}
