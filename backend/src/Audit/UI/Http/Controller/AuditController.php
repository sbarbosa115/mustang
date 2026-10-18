<?php

namespace App\Audit\UI\Http\Controller;

use App\Audit\Application\Query\AuditFilter;
use App\Audit\Application\Query\AuditQueries;
use App\Audit\UI\Http\AuditPresenter;
use App\Audit\UI\Http\Input\AuditQuery;
use App\Audit\UI\Http\Output\AuditPageOutput;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Audit')]
final class AuditController extends ApiController
{
    public function __construct(private readonly AuditQueries $audit, private readonly AuditPresenter $presenter)
    {
    }

    /**
     * Changes and customer record access, newest first (admin). `entity_type` (Customer, CustomerContact, Deal, User…)
     * and `entity_id` narrow it to a record, `user_id` to what one person did; `from` (inclusive) and `to` (exclusive)
     * are an ISO instant or a date meaning midnight at the dealership; 422 `invalid_period`. Passwords, MFA secrets and
     * recovery codes appear as "[masked]".
     */
    #[Route('/api/v1/audit', name: 'api_audit_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Changes and customer record access (admin)', content: new Model(type: AuditPageOutput::class))]
    #[OA\Response(response: 403, description: 'Not an admin', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'A period that is not a date or a time', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function auditList(#[MapQueryString] AuditQuery $query = new AuditQuery()): JsonResponse
    {
        $page = $query->page ?? 1;
        $perPage = $query->perPage ?? 25;
        $zone = null === $query->from && null === $query->to ? null : new \DateTimeZone($this->audit->timezone());
        $filter = new AuditFilter(
            self::text($query->entityType),
            self::text($query->entityId),
            self::text($query->userId),
            self::instant($query->from, 'from', $zone),
            self::instant($query->to, 'to', $zone),
        );
        $result = $this->audit->page($filter, ($page - 1) * $perPage, $perPage);

        return $this->ok(new AuditPageOutput($this->presenter->entries($result['items']), $result['total'], $page, $perPage));
    }

    private static function text(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @throws InvalidValue invalid_period
     */
    private static function instant(?string $value, string $field, ?\DateTimeZone $zone): ?\DateTimeImmutable
    {
        $value = self::text($value);
        if (null === $value || null === $zone) {
            return null;
        }
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)) {
            throw new InvalidValue('invalid_period', \sprintf('"%s" is not a date or a time.', $field), ['field' => $field]);
        }
        try {
            return new \DateTimeImmutable($value, $zone)->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new InvalidValue('invalid_period', \sprintf('"%s" is not a date or a time.', $field), ['field' => $field]);
        }
    }
}
