<?php

namespace App\Customer\UI\Http\Controller;

use App\Customer\Application\Command\AddContact;
use App\Customer\Application\Command\AddNote;
use App\Customer\Application\Command\AddOwnedVehicle;
use App\Customer\Application\Command\ArchiveCustomer;
use App\Customer\Application\Command\AssignTeam;
use App\Customer\Application\Command\CreateCustomer;
use App\Customer\Application\Command\RemoveContact;
use App\Customer\Application\Command\RemoveOwnedVehicle;
use App\Customer\Application\Command\UpdateContact;
use App\Customer\Application\Command\UpdateCustomer;
use App\Customer\Application\Query\CustomerQueries;
use App\Customer\Application\Query\NoteQueries;
use App\Customer\Domain\Model\CustomerNote;
use App\Customer\UI\Http\CustomerPresenter;
use App\Customer\UI\Http\Input\AssignmentsInput;
use App\Customer\UI\Http\Input\ContactInput;
use App\Customer\UI\Http\Input\CreateCustomerInput;
use App\Customer\UI\Http\Input\NoteInput;
use App\Customer\UI\Http\Input\OwnedVehicleInput;
use App\Customer\UI\Http\Input\UpdateContactInput;
use App\Customer\UI\Http\Input\UpdateCustomerInput;
use App\Customer\UI\Http\Output\CustomerOutput;
use App\Customer\UI\Http\Output\NoteOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Application\Port\UserDirectory;
use App\Shared\UI\Http\ApiController;
use App\Shared\UI\Http\Output\UserRefOutput;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[OA\Tag(name: 'Customers')]
final class CustomerWriteController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CustomerQueries $customers,
        private readonly NoteQueries $notes,
        private readonly CustomerPresenter $presenter,
        private readonly UserDirectory $users,
        private readonly TenantContext $tenant,
    ) {
    }

    /**
     * Create a customer.
     */
    #[Route('/api/v1/customers', name: 'api_customers_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Create a customer', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersCreate(#[MapRequestPayload] CreateCustomerInput $input): JsonResponse
    {
        if ((null === $input->phone || '' === trim($input->phone)) && (null === $input->email || '' === trim($input->email))) {
            throw new ValidationFailedException($input, new ConstraintViolationList([new ConstraintViolation('Enter a phone number or an email.', null, [], $input, 'phone', $input->phone)]));
        }
        $id = $this->commands->dispatch(new CreateCustomer(
            (string) $input->firstName,
            (string) $input->lastName,
            $input->phone,
            $input->email,
            $input->preferredChannel,
            $input->sales1UserId,
        ));

        return $this->created($this->presenter->one($this->customers->byId((string) $id)));
    }

    /**
     * Change a customer's name or preferred channel.
     */
    #[Route('/api/v1/customers/{id}', name: 'api_customers_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a customer\'s name or preferred channel', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersUpdate(string $id, #[MapRequestPayload] UpdateCustomerInput $input): JsonResponse
    {
        $this->commands->dispatch(new UpdateCustomer($id, $input->firstName, $input->lastName, $input->preferredChannel));

        return $this->respond($id);
    }

    /**
     * Add a phone number or email.
     */
    #[Route('/api/v1/customers/{id}/contacts', name: 'api_customers_contacts_add', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a phone number or email', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersContactsAdd(string $id, #[MapRequestPayload] ContactInput $input): JsonResponse
    {
        $this->commands->dispatch(new AddContact($id, $input->kind ?? throw new \LogicException('kind is validated'), (string) $input->value, $input->label, true === $input->isPrimary));

        return $this->respond($id, 201);
    }

    /**
     * Change a phone number or email.
     */
    #[Route('/api/v1/customers/{id}/contacts/{contactId}', name: 'api_customers_contacts_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a phone number or email', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersContactsUpdate(string $id, string $contactId, #[MapRequestPayload] UpdateContactInput $input): JsonResponse
    {
        $this->commands->dispatch(new UpdateContact($id, $contactId, $input->value, $input->label, $input->isPrimary));

        return $this->respond($id);
    }

    /**
     * Remove a phone number or email.
     */
    #[Route('/api/v1/customers/{id}/contacts/{contactId}', name: 'api_customers_contacts_remove', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Remove a phone number or email', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersContactsRemove(string $id, string $contactId): JsonResponse
    {
        $this->commands->dispatch(new RemoveContact($id, $contactId));

        return $this->respond($id);
    }

    /**
     * Add a vehicle the customer owns.
     */
    #[Route('/api/v1/customers/{id}/vehicles-owned', name: 'api_customers_owned_add', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a vehicle the customer owns', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersOwnedAdd(string $id, #[MapRequestPayload] OwnedVehicleInput $input): JsonResponse
    {
        $this->commands->dispatch(new AddOwnedVehicle($id, $input->year, $input->make, $input->model, $input->trim, $input->vin, $input->purchasedAt));

        return $this->respond($id, 201);
    }

    /**
     * Remove a vehicle the customer owns.
     */
    #[Route('/api/v1/customers/{id}/vehicles-owned/{vehicleId}', name: 'api_customers_owned_remove', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Remove a vehicle the customer owns', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersOwnedRemove(string $id, string $vehicleId): JsonResponse
    {
        $this->commands->dispatch(new RemoveOwnedVehicle($id, $vehicleId));

        return $this->respond($id);
    }

    /**
     * Assign Sales 1, Sales 2 and BDC; the three slots are set together and a null empties its slot.
     */
    #[Route('/api/v1/customers/{id}/assignments', name: 'api_customers_assign', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Assign Sales 1, Sales 2 and BDC', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersAssign(string $id, #[MapRequestPayload] AssignmentsInput $input): JsonResponse
    {
        $this->commands->dispatch(new AssignTeam($id, $input->sales1UserId, $input->sales2UserId, $input->bdcUserId));

        return $this->respond($id);
    }

    /**
     * The notes on a customer, newest first.
     */
    #[Route('/api/v1/customers/{id}/notes', name: 'api_customers_notes', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The notes on a customer, newest first', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: NoteOutput::class))))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersNotes(string $id): JsonResponse
    {
        $customer = $this->customers->byId($id);

        return $this->json($this->noteOutputs($this->notes->forCustomer($customer->id())));
    }

    /**
     * Add a note.
     */
    #[Route('/api/v1/customers/{id}/notes', name: 'api_customers_notes_add', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a note', content: new Model(type: NoteOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function customersNotesAdd(string $id, #[MapRequestPayload] NoteInput $input): JsonResponse
    {
        $noteId = (string) $this->commands->dispatch(new AddNote($id, (string) $this->tenant->userId(), (string) $input->body));
        $note = array_values(array_filter(
            $this->notes->forCustomer($id),
            static fn (CustomerNote $n): bool => $n->id() === $noteId,
        ))[0];

        return $this->created($this->noteOutputs([$note])[0]);
    }

    /**
     * Archive a customer; archiving twice changes nothing.
     */
    #[Route('/api/v1/customers/{id}/archive', name: 'api_customers_archive', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Archive a customer', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersArchive(string $id): JsonResponse
    {
        $this->commands->dispatch(new ArchiveCustomer($id));

        return $this->respond($id);
    }

    private function respond(string $id, int $status = 200): JsonResponse
    {
        return $this->ok($this->presenter->one($this->customers->byId($id)), $status);
    }

    /**
     * @param list<CustomerNote> $notes
     *
     * @return list<NoteOutput>
     */
    private function noteOutputs(array $notes): array
    {
        $names = $this->users->names(array_map(static fn (CustomerNote $n): string => $n->authorUserId(), $notes));

        return array_map(static fn (CustomerNote $n): NoteOutput => new NoteOutput(
            $n->id(),
            $n->body(),
            $n->createdAt(),
            new UserRefOutput($n->authorUserId(), $names[$n->authorUserId()] ?? ''),
        ), $notes);
    }
}
