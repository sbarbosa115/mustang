import {useQuery} from '@tanstack/react-query';
import {CalendarClock} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {
  AppointmentChip,
  appointmentsKeys,
  listAppointments,
} from '@/entities/appointment';
import {formatDateTime} from '@/shared/lib/dates';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {EmptyState} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';

/** The customer's appointments with their status; the calendar page owns booking and changes. */
export function AppointmentsTab({customerId}: {customerId: string}) {
  const {t} = useTranslation('customers');
  const query = {customer_id: customerId};
  const appointments = useQuery({
    queryKey: appointmentsKeys.list(query),
    queryFn: () => listAppointments(query),
  });

  if (appointments.isLoading) {
    return (
      <Card
        className="gap-2 p-4"
        aria-busy="true"
        aria-label={t('common:list.loading')}
      >
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
      </Card>
    );
  }
  if (appointments.isError) {
    return (
      <Card>
        <EmptyState
          icon={CalendarClock}
          title={t('detail.appointments_error')}
          action={
            <Button
              variant="outline"
              onClick={() => void appointments.refetch()}
            >
              {t('common:action.retry')}
            </Button>
          }
        />
      </Card>
    );
  }
  if (!appointments.data || appointments.data.items.length === 0) {
    return (
      <Card>
        <EmptyState
          icon={CalendarClock}
          title={t('detail.appointments_empty')}
        />
      </Card>
    );
  }
  return (
    <Card className="gap-0 py-0">
      <ul className="divide-y">
        {appointments.data.items.map((appointment) => (
          <li
            key={appointment.id}
            className="flex flex-wrap items-center gap-x-3 gap-y-1 p-4 text-sm"
          >
            <span className="font-medium">
              {t(`detail.kind.${appointment.kind}`)}
            </span>
            <span className="tabular text-muted-foreground">
              {formatDateTime(appointment.scheduled_at)}
            </span>
            <AppointmentChip appointment={appointment} />
            <span className="text-muted-foreground">
              {t('detail.with_user', {name: appointment.user.name})}
            </span>
          </li>
        ))}
      </ul>
    </Card>
  );
}
