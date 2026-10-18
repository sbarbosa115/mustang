<?php

namespace App\Appointment\UI\Http\Controller;

use App\Appointment\UI\Http\Input\AppointmentListQuery;
use App\Appointment\UI\Http\Input\BookAppointmentInput;
use App\Appointment\UI\Http\Input\UpdateAppointmentInput;
use App\Appointment\UI\Http\Output\AppointmentListOutput;
use App\Appointment\UI\Http\Output\AppointmentOutput;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contract stub: routes, inputs and outputs are fixed by item 0; item "appointments" implements them.
 */
#[OA\Tag(name: 'Appointments')]
final class AppointmentController extends ApiController
{
    /**
     * Appointments in a period.
     */
    #[Route('/api/v1/appointments', name: 'api_appointments_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Appointments in a period', content: new Model(type: AppointmentListOutput::class))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsList(#[MapQueryString] AppointmentListQuery $query = new AppointmentListQuery()): JsonResponse
    {
        unset($query);

        return $this->notImplemented('appointments');
    }

    /**
     * Book an appointment.
     */
    #[Route('/api/v1/appointments', name: 'api_appointments_book', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Book an appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsBook(#[MapRequestPayload] BookAppointmentInput $input): JsonResponse
    {
        unset($input);

        return $this->notImplemented('appointments');
    }

    /**
     * One appointment.
     */
    #[Route('/api/v1/appointments/{id}', name: 'api_appointments_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsGet(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('appointments');
    }

    /**
     * Reschedule or reassign an appointment.
     */
    #[Route('/api/v1/appointments/{id}', name: 'api_appointments_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Reschedule or reassign an appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsUpdate(string $id, #[MapRequestPayload] UpdateAppointmentInput $input): JsonResponse
    {
        unset($id);
        unset($input);

        return $this->notImplemented('appointments');
    }

    /**
     * Mark an appointment confirmed.
     */
    #[Route('/api/v1/appointments/{id}/confirm', name: 'api_appointments_confirm', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark an appointment confirmed', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsConfirm(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('appointments');
    }

    /**
     * Mark that the customer showed.
     */
    #[Route('/api/v1/appointments/{id}/show', name: 'api_appointments_show', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark that the customer showed', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsShow(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('appointments');
    }

    /**
     * Mark a no-show.
     */
    #[Route('/api/v1/appointments/{id}/no-show', name: 'api_appointments_no_show', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark a no-show', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsNoShow(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('appointments');
    }

    /**
     * Cancel an appointment.
     */
    #[Route('/api/v1/appointments/{id}/cancel', name: 'api_appointments_cancel', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Cancel an appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 501, description: 'Not built yet (item "appointments")', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsCancel(string $id): JsonResponse
    {
        unset($id);

        return $this->notImplemented('appointments');
    }
}
