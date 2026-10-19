import {Ellipsis} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {AppointmentChip, type Appointment} from '@/entities/appointment';
import {
  ConfirmAppointmentButton,
  SendConfirmationButton,
} from '@/features/confirm-appointment';
import {MarkNoShowButton, MarkShownButton} from '@/features/mark-shown';
import {Button} from '@/shared/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/shared/ui/dropdown-menu';

interface Props {
  appointment: Appointment;
  timeZone: string;
  onReschedule: (appointment: Appointment) => void;
  onCancel: (appointment: Appointment) => void;
}

/** One appointment: when, who, with whom, and what the rep can do next. */
export function AppointmentCard({
  appointment,
  timeZone,
  onReschedule,
  onCancel,
}: Props) {
  const {t} = useTranslation('appointments');
  const open =
    appointment.status === 'scheduled' || appointment.status === 'confirmed';
  return (
    <article
      aria-label={t('card.label', {
        name: appointment.customer.name,
      })}
      className="flex flex-col gap-2 rounded-lg border bg-card p-3 text-sm shadow-xs"
    >
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
        <AppointmentChip appointment={appointment} timeZone={timeZone} />
        <span className="tabular text-xs text-muted-foreground">
          {t('card.length', {count: appointment.duration_minutes})}
        </span>
      </div>
      <div className="space-y-0.5">
        <Link
          to={`/customers/${appointment.customer.id}`}
          className="font-medium underline-offset-4 hover:underline"
        >
          {appointment.customer.name}
        </Link>
        <p className="text-muted-foreground">
          {t(`kind.${appointment.kind}`)} ·{' '}
          {t('card.with_rep', {name: appointment.user.name})}
        </p>
        {appointment.note && (
          <p className="line-clamp-2 text-muted-foreground">
            {appointment.note}
          </p>
        )}
      </div>
      {open && (
        <div className="flex flex-wrap items-center gap-2">
          {appointment.status === 'scheduled' && (
            <ConfirmAppointmentButton appointment={appointment} />
          )}
          <MarkShownButton appointment={appointment} />
          <MarkNoShowButton appointment={appointment} />
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label={t('card.actions', {
                  name: appointment.customer.name,
                })}
              >
                <Ellipsis aria-hidden />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onSelect={() => onReschedule(appointment)}>
                {t('card.reschedule')}
              </DropdownMenuItem>
              <DropdownMenuItem
                variant="destructive"
                onSelect={() => onCancel(appointment)}
              >
                {t('card.cancel')}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      )}
      {appointment.status === 'scheduled' && (
        <div>
          <SendConfirmationButton appointment={appointment} />
        </div>
      )}
    </article>
  );
}
