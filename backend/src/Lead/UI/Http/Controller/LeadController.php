<?php

namespace App\Lead\UI\Http\Controller;

use App\Lead\Application\Command\AssignLead;
use App\Lead\Application\Command\CaptureLead;
use App\Lead\Application\Command\CloseLead;
use App\Lead\Application\Query\LeadQueries;
use App\Lead\UI\Http\Input\CaptureLeadInput;
use App\Lead\UI\Http\Input\CloseLeadInput;
use App\Lead\UI\Http\Input\LeadAssigneeInput;
use App\Lead\UI\Http\Input\LeadListQuery;
use App\Lead\UI\Http\LeadPresenter;
use App\Lead\UI\Http\Output\LeadOutput;
use App\Lead\UI\Http\Output\LeadPageOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[OA\Tag(name: 'Leads')]
final class LeadController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly LeadQueries $leads,
        private readonly LeadPresenter $presenter,
    ) {
    }

    /**
     * Leads, newest first.
     */
    #[Route('/api/v1/leads', name: 'api_leads_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Leads, newest first', content: new Model(type: LeadPageOutput::class))]
    public function leadsList(#[MapQueryString] LeadListQuery $query = new LeadListQuery()): JsonResponse
    {
        $page = $query->page ?? 1;
        $perPage = $query->perPage ?? 25;
        $term = null === $query->q ? null : trim($query->q);
        $result = $this->leads->page(($page - 1) * $perPage, $perPage, '' === $term ? null : $term, $query->status, $query->assignedTo, $query->source);

        return $this->ok(new LeadPageOutput($this->presenter->list($result['items']), $result['total'], $page, $perPage));
    }

    /**
     * Capture a lead: match or create the customer, route it to a rep.
     */
    #[Route('/api/v1/leads', name: 'api_leads_capture', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Capture a lead: match or create the customer, route it to a rep', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function leadsCapture(#[MapRequestPayload] CaptureLeadInput $input): JsonResponse
    {
        if (null === $input->customerId && self::blank($input->phone) && self::blank($input->email)) {
            $this->refuse($input, 'phone', 'Enter a phone number or an email.', $input->phone);
        }
        $receivedAt = null;
        if (!self::blank($input->receivedAt)) {
            try {
                $receivedAt = new \DateTimeImmutable((string) $input->receivedAt)->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Exception) {
                $this->refuse($input, 'received_at', 'Enter a date and time.', $input->receivedAt);
            }
        }
        $id = $this->commands->dispatch(new CaptureLead(
            $input->source ?? throw new \LogicException('source is validated'),
            $input->customerId,
            $input->firstName,
            $input->lastName,
            $input->phone,
            $input->email,
            $input->channelNote,
            $input->vehicleInterestText,
            $input->assignedUserId,
            $receivedAt,
        ));

        return $this->created($this->presenter->one($this->leads->byId((string) $id)));
    }

    /**
     * One lead.
     */
    #[Route('/api/v1/leads/{id}', name: 'api_leads_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One lead', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsGet(string $id): JsonResponse
    {
        return $this->ok($this->presenter->one($this->leads->byId($id)));
    }

    /**
     * Assign a lead to a rep.
     */
    #[Route('/api/v1/leads/{id}/assignee', name: 'api_leads_assign', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Assign a lead to a rep', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function leadsAssign(string $id, #[MapRequestPayload] LeadAssigneeInput $input): JsonResponse
    {
        $this->commands->dispatch(new AssignLead($id, (string) $input->userId));

        return $this->ok($this->presenter->one($this->leads->byId($id)));
    }

    /**
     * Close a lead.
     */
    #[Route('/api/v1/leads/{id}/close', name: 'api_leads_close', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Close a lead', content: new Model(type: LeadOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function leadsClose(string $id, #[MapRequestPayload] CloseLeadInput $input = new CloseLeadInput()): JsonResponse
    {
        // The reason has no column in the MVP schema; it is accepted and not stored.
        unset($input);
        $this->commands->dispatch(new CloseLead($id));

        return $this->ok($this->presenter->one($this->leads->byId($id)));
    }

    private static function blank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }

    private function refuse(object $input, string $field, string $message, mixed $value): never
    {
        throw new ValidationFailedException($input, new ConstraintViolationList([new ConstraintViolation($message, null, [], $input, $field, $value)]));
    }
}
