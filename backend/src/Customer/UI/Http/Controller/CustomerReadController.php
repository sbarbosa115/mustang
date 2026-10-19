<?php

namespace App\Customer\UI\Http\Controller;

use App\Customer\Application\Query\CustomerQueries;
use App\Customer\Domain\Event\CustomerViewed;
use App\Customer\UI\Http\CustomerPresenter;
use App\Customer\UI\Http\Input\CustomerListQuery;
use App\Customer\UI\Http\Input\CustomerLookupQuery;
use App\Customer\UI\Http\Output\CustomerLookupOutput;
use App\Customer\UI\Http\Output\CustomerOutput;
use App\Customer\UI\Http\Output\CustomerPageOutput;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Port\Clock;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Email;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\Domain\PhoneNumber;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Customers')]
final class CustomerReadController extends ApiController
{
    public function __construct(
        private readonly CustomerQueries $customers,
        private readonly CustomerPresenter $presenter,
        private readonly EventBus $events,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
    ) {
    }

    /**
     * The dealership's customers, most recently updated first; q matches a name, phone or email.
     */
    #[Route('/api/v1/customers', name: 'api_customers_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A page of customers', content: new Model(type: CustomerPageOutput::class))]
    public function list(#[MapQueryString] CustomerListQuery $query = new CustomerListQuery()): JsonResponse
    {
        $page = $query->page ?? 1;
        $perPage = $query->perPage ?? 25;
        $term = null === $query->q || '' === trim($query->q) ? null : trim($query->q);
        $result = $this->customers->page(($page - 1) * $perPage, $perPage, $term, $query->assignedTo, $query->archived ?? false);

        return $this->ok(new CustomerPageOutput($this->presenter->list($result['items']), $result['total'], $page, $perPage));
    }

    /**
     * Customers matching a phone or email, so a form can offer the existing record instead of a duplicate.
     */
    #[Route('/api/v1/customers/lookup', name: 'api_customers_lookup', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Matching customers (at most 10)', content: new Model(type: CustomerLookupOutput::class))]
    public function lookup(#[MapQueryString] CustomerLookupQuery $query = new CustomerLookupQuery()): JsonResponse
    {
        $phone = self::normalized(static fn (string $v): string => PhoneNumber::fromString($v)->e164, $query->phone);
        $email = self::normalized(static fn (string $v): string => Email::fromString($v)->value, $query->email);

        return $this->ok(new CustomerLookupOutput($this->presenter->list($this->customers->findByContact($phone, $email))));
    }

    /**
     * One customer with contacts, owned vehicles, team, open deal and consent per channel.
     */
    #[Route('/api/v1/customers/{id}', name: 'api_customers_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The customer', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function get(string $id, Request $request): JsonResponse
    {
        $customer = $this->customers->byId($id);
        $this->events->publish(new CustomerViewed(
            $customer->id(),
            $customer->dealershipId(),
            $this->tenant->userId(),
            $request->getClientIp(),
            $request->headers->get('X-Request-Id'),
            $this->clock->now(),
        ));

        return $this->ok($this->presenter->one($customer));
    }

    /**
     * A malformed phone or email in a lookup is simply no match, not an error.
     *
     * @param callable(string): string $normalize
     */
    private static function normalized(callable $normalize, ?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }
        try {
            return $normalize($value);
        } catch (InvalidValue) {
            return null;
        }
    }
}
