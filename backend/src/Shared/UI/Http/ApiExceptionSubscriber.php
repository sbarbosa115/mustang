<?php

namespace App\Shared\UI\Http;

use App\Shared\Domain\Error\DomainError;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Every error under /api becomes {error, message, detail}: the UI shows the translation of "error".
 * Validation failures are 422 {error: "validation", violations: [{field, code, message}]}.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class ApiExceptionSubscriber
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $e = $event->getThrowable();
        if ($e instanceof AccessDeniedException || $e instanceof AuthenticationException) {
            // The firewall answers these: 401 through the entry point when signed out, 403 when signed in.
            return;
        }
        if ($e instanceof HandlerFailedException && null !== $e->getPrevious()) {
            $e = $e->getPrevious();
        }

        $response = match (true) {
            $e instanceof DomainError => $this->body($e->httpStatus(), $e->errorCode(), $e->getMessage(), $e->detail()),
            null !== ($violations = $this->violations($e)) => $this->validation($violations),
            $e instanceof HttpExceptionInterface => $this->http($e),
            default => null,
        };

        if (null === $response) {
            $this->logger->error('Unhandled API error', ['exception' => $e]);

            return;
        }
        $event->setResponse($response);
    }

    private function violations(\Throwable $e): ?ConstraintViolationListInterface
    {
        for ($cause = $e; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof ValidationFailedException) {
                return $cause->getViolations();
            }
        }

        return null;
    }

    private function validation(ConstraintViolationListInterface $violations): JsonResponse
    {
        $items = [];
        foreach ($violations as $violation) {
            $items[] = [
                'field' => self::snake($violation->getPropertyPath()),
                'code' => $this->violationCode($violation->getCode()),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return new JsonResponse([
            'error' => 'validation',
            'message' => 'Some fields need attention.',
            'violations' => $items,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $code = match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            413 => 'too_large',
            415 => 'unsupported_media_type',
            429 => 'too_many_attempts',
            default => 'http_'.$status,
        };

        return $this->body($status, $code, $status >= 500 ? 'Something went wrong.' : $e->getMessage());
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function body(int $status, string $code, string $message, array $detail = []): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message, 'detail' => (object) $detail], $status);
    }

    private function violationCode(?string $uuidCode): string
    {
        return match ($uuidCode) {
            'c1051bb4-d103-4f74-8988-acbcafc7fdc3', 'ad32d13f-c3d4-423b-909a-857b961eb720' => 'required',
            '9ff3fdc4-b214-49db-8718-39c315e33d45', 'd94b19cc-114f-4f44-9cc4-4138e80a87b9' => 'too_short',
            '35e6a710-aa2e-4719-b58e-24b35749b767', '8f900c12-61bd-455d-9398-996cd040f7f0' => 'too_long',
            'bd79c0ab-ddba-46cc-a703-a7a4b08de310' => 'invalid_email',
            '8e179f1b-97aa-4560-a02f-2a8b42e49df7' => 'invalid_choice',
            default => 'invalid',
        };
    }

    private static function snake(string $path): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $path));
    }
}
