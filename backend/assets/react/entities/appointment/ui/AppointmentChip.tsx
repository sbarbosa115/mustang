import {CalendarClock} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {formatTime} from '@/shared/lib/dates';
import {cn} from '@/shared/lib/utils';

const STATUS_CLASS: Record<string, string> = {
  scheduled: 'bg-muted text-foreground',
  confirmed: 'bg-success-surface text-success',
  shown: 'bg-stage-visit-surface text-stage-visit',
  no_show: 'bg-danger-surface text-danger',
  cancelled: 'bg-muted text-muted-foreground line-through',
};

/** An appointment's time and status as a small chip, in the dealership timezone. */
export function AppointmentChip({
  appointment,
  timeZone,
}: {
  appointment: Pick<Schema<'AppointmentOutput'>, 'scheduled_at' | 'status'>;
  timeZone?: string;
}) {
  const {t} = useTranslation('appointments');
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
        STATUS_CLASS[appointment.status],
      )}
    >
      <CalendarClock aria-hidden className="size-3.5" />
      <span className="tabular">
        {formatTime(appointment.scheduled_at, timeZone)}
      </span>
      <span>{t(`status.${appointment.status}`)}</span>
    </span>
  );
}
