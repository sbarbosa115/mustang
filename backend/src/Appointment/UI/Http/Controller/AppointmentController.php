<?php

namespace App\Appointment\UI\Http\Controller;

use App\Appointment\Application\Command\BookAppointment;
use App\Appointment\Application\Command\CancelAppointment;
use App\Appointment\Application\Command\ConfirmAppointment;
use App\Appointment\Application\Command\MarkNoShow;
use App\Appointment\Application\Command\MarkShown;
use App\Appointment\Application\Command\RescheduleAppointment;
use App\Appointment\Application\Port\AppointmentPeople;
use App\Appointment\Application\Query\AppointmentFilter;
use App\Appointment\Application\Query\AppointmentQueries;
use App\Appointment\UI\Http\AppointmentPresenter;
use App\Appointment\UI\Http\Input\AppointmentListQuery;
use App\Appointment\UI\Http\Input\BookAppointmentInput;
use App\Appointment\UI\Http\Input\UpdateAppointmentInput;
use App\Appointment\UI\Http\Output\AppointmentListOutput;
use App\Appointment\UI\Http\Output\AppointmentOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Port\TenantContext;
use App\Shared\Domain\Error\InvalidValue;
use App\Shared\UI\Http\ApiController;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Appointments')]
final class AppointmentController extends ApiController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AppointmentQueries $appointments,
        private readonly AppointmentPresenter $presenter,
        private readonly AppointmentPeople $people,
        private readonly TenantContext $tenant,
    ) {
    }

    /**
     * Appointments in a period, by time. `from` is inclusive and `to` exclusive: an ISO instant, or a date
     * (2026-10-20) meaning midnight in the dealership's timezone. At most 500.
     */
    #[Route('/api/v1/appointments', name: 'api_appointments_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Appointments in a period', content: new Model(type: AppointmentListOutput::class))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function appointmentsList(#[MapQueryString] AppointmentListQuery $query = new AppointmentListQuery()): JsonResponse
    {
        $zone = new \DateTimeZone($this->people->timezone($this->tenant->dealershipId()));
        $filter = new AppointmentFilter(
            $this->instant($query->from, 'from', $zone),
            $this->instant($query->to, 'to', $zone),
            $query->userId,
            $query->customerId,
        );

        return $this->ok($this->presenter->list($this->appointments->list($filter)));
    }

    /**
     * Book an appointment. `scheduled_at` is local time in the dealership's timezone (2026-10-20T15:30); the rep
     * defaults to the signed-in user.
     */
    #[Route('/api/v1/appointments', name: 'api_appointments_book', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Book an appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function appointmentsBook(#[MapRequestPayload] BookAppointmentInput $input): JsonResponse
    {
        $id = (string) $this->commands->dispatch(new BookAppointment(
            $this->tenant->dealershipId(),
            (string) $input->customerId,
            $input->userId ?? (string) $this->tenant->userId(),
            (string) $input->scheduledAt,
            $input->dealId,
            $input->durationMinutes,
            $input->kind,
            $input->note,
        ));

        return $this->created($this->presenter->one($this->appointments->byId($id)));
    }

    /**
     * One appointment.
     */
    #[Route('/api/v1/appointments/{id}', name: 'api_appointments_get', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'One appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsGet(string $id): JsonResponse
    {
        return $this->respond($id);
    }

    /**
     * Reschedule or reassign an appointment, or change its length, kind or note; fields left out stay as they are.
     */
    #[Route('/api/v1/appointments/{id}', name: 'api_appointments_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Reschedule or reassign an appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 422, description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
    public function appointmentsUpdate(string $id, #[MapRequestPayload] UpdateAppointmentInput $input): JsonResponse
    {
        $this->commands->dispatch(new RescheduleAppointment($id, $input->scheduledAt, $input->userId, $input->durationMinutes, $input->kind, $input->note));

        return $this->respond($id);
    }

    /**
     * Mark an appointment confirmed.
     */
    #[Route('/api/v1/appointments/{id}/confirm', name: 'api_appointments_confirm', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark an appointment confirmed', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsConfirm(string $id): JsonResponse
    {
        $this->commands->dispatch(new ConfirmAppointment($id));

        return $this->respond($id);
    }

    /**
     * Mark that the customer showed.
     */
    #[Route('/api/v1/appointments/{id}/show', name: 'api_appointments_show', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark that the customer showed', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsShow(string $id): JsonResponse
    {
        $this->commands->dispatch(new MarkShown($id));

        return $this->respond($id);
    }

    /**
     * Mark a no-show.
     */
    #[Route('/api/v1/appointments/{id}/no-show', name: 'api_appointments_no_show', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Mark a no-show', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsNoShow(string $id): JsonResponse
    {
        $this->commands->dispatch(new MarkNoShow($id));

        return $this->respond($id);
    }

    /**
     * Cancel an appointment.
     */
    #[Route('/api/v1/appointments/{id}/cancel', name: 'api_appointments_cancel', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Cancel an appointment', content: new Model(type: AppointmentOutput::class))]
    #[OA\Response(response: 404, description: 'Not found, or another dealership\'s record', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    #[OA\Response(response: 409, description: 'Refused by a rule (see error)', content: new OA\JsonContent(ref: '#/components/schemas/ApiError'))]
    public function appointmentsCancel(string $id): JsonResponse
    {
        $this->commands->dispatch(new CancelAppointment($id));

        return $this->respond($id);
    }

    private function respond(string $id): JsonResponse
    {
        return $this->ok($this->presenter->one($this->appointments->byId($id)));
    }

    /**
     * @throws InvalidValue invalid_period
     */
    private function instant(?string $value, string $field, \DateTimeZone $zone): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }
        try {
            return new \DateTimeImmutable(trim($value), $zone)->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new InvalidValue('invalid_period', \sprintf('"%s" is not a date or a time.', $field), ['field' => $field]);
        }
    }
}
