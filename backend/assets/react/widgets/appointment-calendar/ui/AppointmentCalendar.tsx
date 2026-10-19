import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {CalendarClock, ChevronLeft, ChevronRight, SearchX} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {
  appointmentsKeys,
  listAppointments,
  type Appointment,
} from '@/entities/appointment';
import {
  CancelAppointmentDialog,
  RescheduleDialog,
} from '@/features/reschedule-appointment';
import {cn} from '@/shared/lib/utils';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {Label} from '@/shared/ui/label';
import {EmptyState} from '@/shared/ui/mustang';
import {RadioGroup, RadioGroupItem} from '@/shared/ui/radio-group';
import {Skeleton} from '@/shared/ui/skeleton';
import {
  dayLabel,
  dayOf,
  isDate,
  rangeLabels,
  rangeOf,
  shift,
  todayIn,
  type CalendarView,
} from '../lib/range';
import {AppointmentCard} from './AppointmentCard';

const VIEWS: CalendarView[] = ['day', 'week'];
const DEFAULT_TIME_ZONE = 'America/New_York';

interface Props {
  /** The signed-in user, for "Mine". */
  userId?: string;
  /** The dealership's timezone: days start and end on its clock. */
  timeZone?: string;
}

/**
 * The team's appointments by day, for a day or a week (Monday to Sunday), at the dealership's time. The view, the date
 * and whose appointments live in the URL, so a link or a reload keeps them. Cancelled appointments are not shown.
 */
export function AppointmentCalendar({
  userId,
  timeZone = DEFAULT_TIME_ZONE,
}: Props) {
  const {t} = useTranslation('appointments');
  const [params, setParams] = useSearchParams();
  const view: CalendarView =
    VIEWS.find((v) => v === params.get('view')) ?? 'week';
  const today = todayIn(timeZone);
  const requested = params.get('date');
  const date = isDate(requested) ? requested : today;
  const mine = params.get('scope') === 'mine' && !!userId;
  const range = rangeOf(date, view);
  const [rescheduling, setRescheduling] = useState<Appointment | null>(null);
  const [cancelling, setCancelling] = useState<Appointment | null>(null);

  const set = (changes: Record<string, string | null>) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        for (const [key, value] of Object.entries(changes)) {
          if (value === null || value === '') next.delete(key);
          else next.set(key, value);
        }
        return next;
      },
      {replace: true},
    );

  const query = {
    from: range.from,
    to: range.to,
    ...(mine ? {user_id: userId} : {}),
  };
  const appointments = useQuery({
    queryKey: appointmentsKeys.list(query),
    queryFn: () => listAppointments(query),
    placeholderData: keepPreviousData,
  });

  const visible = (appointments.data?.items ?? []).filter(
    (a) => a.status !== 'cancelled',
  );
  const byDay = new Map<string, Appointment[]>();
  for (const a of visible) {
    const key = dayOf(a.scheduled_at, timeZone);
    byDay.set(key, [...(byDay.get(key) ?? []), a]);
  }
  const labels = rangeLabels(range);
  const previousLabel = t(
    view === 'day' ? 'calendar.previous_day' : 'calendar.previous_week',
  );
  const nextLabel = t(
    view === 'day' ? 'calendar.next_day' : 'calendar.next_week',
  );

  const segmented = (
    name: string,
    label: string,
    value: string,
    options: Array<{value: string; label: string}>,
    onChange: (value: string) => void,
  ) => (
    <fieldset>
      <legend className="sr-only">{label}</legend>
      <RadioGroup
        value={value}
        onValueChange={onChange}
        className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
      >
        {options.map((o) => (
          <Label
            key={o.value}
            htmlFor={`${name}_${o.value}`}
            className="flex min-h-9 cursor-pointer items-center rounded-md px-3 text-sm font-medium text-muted-foreground has-[[data-state=checked]]:bg-background has-[[data-state=checked]]:text-foreground has-[[data-state=checked]]:shadow-sm"
          >
            <RadioGroupItem
              id={`${name}_${o.value}`}
              value={o.value}
              className="sr-only"
            />
            {o.label}
          </Label>
        ))}
      </RadioGroup>
    </fieldset>
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label={previousLabel}
            onClick={() => set({date: shift(date, view, -1)})}
          >
            <ChevronLeft aria-hidden />
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => set({date: null})}
            disabled={range.days.includes(today)}
          >
            {t('calendar.today')}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="icon"
            aria-label={nextLabel}
            onClick={() => set({date: shift(date, view, 1)})}
          >
            <ChevronRight aria-hidden />
          </Button>
        </div>
        <h2 className="text-lg font-semibold lg:flex-1">
          {view === 'day'
            ? dayLabel(date)
            : t('calendar.range', {from: labels.from, to: labels.to})}
        </h2>
        <div className="flex flex-wrap gap-2">
          {segmented(
            'calendar_view',
            t('calendar.view.label'),
            view,
            VIEWS.map((v) => ({value: v, label: t(`calendar.view.${v}`)})),
            (v) => set({view: v === 'week' ? null : v}),
          )}
          {userId &&
            segmented(
              'calendar_scope',
              t('calendar.scope.label'),
              mine ? 'mine' : 'all',
              [
                {value: 'all', label: t('calendar.scope.all')},
                {value: 'mine', label: t('calendar.scope.mine')},
              ],
              (v) => set({scope: v === 'mine' ? 'mine' : null}),
            )}
        </div>
      </div>

      {appointments.isPending ? (
        <div
          aria-busy="true"
          aria-label={t('calendar.loading')}
          className="space-y-3"
        >
          {range.days.map((d) => (
            <Skeleton key={d} className="h-24 w-full" />
          ))}
        </div>
      ) : appointments.isError ? (
        <Card>
          <EmptyState
            icon={CalendarClock}
            title={t('calendar.error')}
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
      ) : visible.length === 0 ? (
        <Card>
          {mine ? (
            <EmptyState
              icon={SearchX}
              title={t('calendar.empty_mine')}
              action={
                <Button variant="outline" onClick={() => set({scope: null})}>
                  {t('common:list.show_all')}
                </Button>
              }
            />
          ) : (
            <EmptyState
              icon={CalendarClock}
              title={t('calendar.empty')}
              description={t('calendar.empty_help')}
            />
          )}
        </Card>
      ) : (
        <div
          role="group"
          aria-label={t('calendar.label')}
          className={cn(
            'space-y-4',
            appointments.isPlaceholderData && 'opacity-60',
          )}
        >
          {range.days.map((d) => {
            const items = byDay.get(d) ?? [];
            const isToday = d === today;
            return (
              <section
                key={d}
                aria-label={dayLabel(d)}
                className="grid gap-2 md:grid-cols-[9rem_1fr]"
              >
                <h3 className="flex items-baseline gap-2 text-sm font-medium md:flex-col md:gap-0.5">
                  <span>{dayLabel(d)}</span>
                  <span className="text-xs font-normal text-muted-foreground">
                    {isToday
                      ? t('calendar.today_marker')
                      : t('calendar.day_count', {count: items.length})}
                  </span>
                </h3>
                {items.length === 0 ? (
                  <p className="rounded-lg border border-dashed px-3 py-3 text-sm text-muted-foreground">
                    {t('calendar.day_empty')}
                  </p>
                ) : (
                  <div className="grid gap-2 lg:grid-cols-2 xl:grid-cols-3">
                    {items.map((a) => (
                      <AppointmentCard
                        key={a.id}
                        appointment={a}
                        timeZone={timeZone}
                        onReschedule={setRescheduling}
                        onCancel={setCancelling}
                      />
                    ))}
                  </div>
                )}
              </section>
            );
          })}
        </div>
      )}

      {rescheduling && (
        <RescheduleDialog
          appointment={rescheduling}
          open
          onOpenChange={(open) => !open && setRescheduling(null)}
        />
      )}
      {cancelling && (
        <CancelAppointmentDialog
          appointment={cancelling}
          open
          onOpenChange={(open) => !open && setCancelling(null)}
        />
      )}
    </div>
  );
}
