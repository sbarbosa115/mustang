<?php

namespace App\Customer\UI\Http\Controller;

use App\Customer\UI\Http\Input\AssignmentsInput;
use App\Customer\UI\Http\Input\ContactInput;
use App\Customer\UI\Http\Input\CreateCustomerInput;
use App\Customer\UI\Http\Input\NoteInput;
use App\Customer\UI\Http\Input\OwnedVehicleInput;
use App\Customer\UI\Http\Input\UpdateContactInput;
use App\Customer\UI\Http\Input\UpdateCustomerInput;
use App\Customer\UI\Http\Output\CustomerOutput;
use App\Customer\UI\Http\Output\NoteOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "customers" implements them.
 */
#[OA\Tag(name: 'Customers')]
final class CustomerWriteController extends ApiController
{
    /**
     * Create a customer.
     */
    #[Route('/api/v1/customers', name: 'api_customers_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Create a customer', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersCreate(#[MapRequestPayload] CreateCustomerInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * Change a customer's name or preferred channel.
     */
    #[Route('/api/v1/customers/{id}', name: 'api_customers_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a customer\'s name or preferred channel', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersUpdate(string $id, #[MapRequestPayload] UpdateCustomerInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * Add a phone number or email.
     */
    #[Route('/api/v1/customers/{id}/contacts', name: 'api_customers_contacts_add', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a phone number or email', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersContactsAdd(string $id, #[MapRequestPayload] ContactInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * Change a phone number or email.
     */
    #[Route('/api/v1/customers/{id}/contacts/{contactId}', name: 'api_customers_contacts_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Change a phone number or email', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersContactsUpdate(string $id, string $contactId, #[MapRequestPayload] UpdateContactInput $input): JsonResponse
    {
        unset($id);
        unset($contactId);
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * Remove a phone number or email.
     */
    #[Route('/api/v1/customers/{id}/contacts/{contactId}', name: 'api_customers_contacts_remove', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Remove a phone number or email', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersContactsRemove(string $id, string $contactId): JsonResponse
    {
        unset($id);
        unset($contactId);

        return $this->notImplemented('customers');
    }

    /**
     * Add a vehicle the customer owns.
     */
    #[Route('/api/v1/customers/{id}/vehicles-owned', name: 'api_customers_owned_add', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a vehicle the customer owns', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersOwnedAdd(string $id, #[MapRequestPayload] OwnedVehicleInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * Remove a vehicle the customer owns.
     */
    #[Route('/api/v1/customers/{id}/vehicles-owned/{vehicleId}', name: 'api_customers_owned_remove', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Remove a vehicle the customer owns', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersOwnedRemove(string $id, string $vehicleId): JsonResponse
    {
        unset($id);
        unset($vehicleId);

        return $this->notImplemented('customers');
    }

    /**
     * Assign Sales 1, Sales 2 and BDC.
     */
    #[Route('/api/v1/customers/{id}/assignments', name: 'api_customers_assign', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Assign Sales 1, Sales 2 and BDC', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersAssign(string $id, #[MapRequestPayload] AssignmentsInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * The notes on a customer, newest first.
     */
    #[Route('/api/v1/customers/{id}/notes', name: 'api_customers_notes', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The notes on a customer, newest first', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: NoteOutput::class))))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersNotes(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('customers');
    }

    /**
     * Add a note.
     */
    #[Route('/api/v1/customers/{id}/notes', name: 'api_customers_notes_add', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Add a note', content: new Model(type: NoteOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersNotesAdd(string $id, #[MapRequestPayload] NoteInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('customers');
    }

    /**
     * Archive a customer.
     */
    #[Route('/api/v1/customers/{id}/archive', name: 'api_customers_archive', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Archive a customer', content: new Model(type: CustomerOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "customers")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function customersArchive(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('customers');
    }
}
