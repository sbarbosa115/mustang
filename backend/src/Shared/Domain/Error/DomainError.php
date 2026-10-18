<?php

namespace App\Shared\Domain\Error;

/**
 * A rule's "no". The API turns each kind into its HTTP status and {error, message, detail}.
 */
abstract class DomainError extends \DomainException
{
    /**
     * @param array<string, mixed> $detail
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $detail = [],
    ) {
        parent::__construct($message);
    }

    abstract public function httpStatus(): int;

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(): array
    {
        return $this->detail;
    }
}
