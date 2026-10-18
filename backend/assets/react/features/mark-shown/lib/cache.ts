import type {QueryClient} from '@tanstack/react-query';
import {appointmentsKeys, type Appointment} from '@/entities/appointment';

/**
 * After a change: the appointment's own query gets the answer, every list refetches, and so do the customer's
 * record and the deals (booking, showing and a no-show move the deal).
 */
export function appointmentChanged(
  queryClient: QueryClient,
  appointment: Appointment,
): void {
  queryClient.setQueryData(
    appointmentsKeys.detail(appointment.id),
    appointment,
  );
  void queryClient.invalidateQueries({queryKey: ['appointments', 'list']});
  void queryClient.invalidateQueries({
    queryKey: ['customers', 'detail', appointment.customer.id],
  });
  void queryClient.invalidateQueries({queryKey: ['deals']});
}
