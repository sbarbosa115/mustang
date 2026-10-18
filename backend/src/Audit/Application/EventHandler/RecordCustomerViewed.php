<?php

namespace App\Audit\Application\EventHandler;

use App\Audit\Application\Command\RecordAccess;
use App\Audit\Application\Port\RequestContext;
use App\Customer\Domain\Event\CustomerViewed;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * Opening a customer record is recorded as a read of that customer. The request's own id and address win over the
 * event's copies: the request context has already checked the caller's X-Request-Id (or made one).
 */
final readonly class RecordCustomerViewed implements EventHandler
{
    public const string ENTITY_TYPE = 'Customer';

    public function __construct(private CommandBus $commands, private RequestContext $request)
    {
    }

    public function __invoke(CustomerViewed $event): void
    {
        $this->commands->dispatch(new RecordAccess(
            $event->dealershipId,
            self::ENTITY_TYPE,
            $event->customerId,
            $event->customerId,
            $event->viewerUserId,
            $this->request->ip() ?? self::clip($event->ip, 45),
            $this->request->requestId() ?? self::clip($event->requestId, 64),
            $event->occurredAt,
        ));
    }

    private static function clip(?string $value, int $length): ?string
    {
        return null === $value || '' === $value ? null : mb_substr($value, 0, $length);
    }
}
