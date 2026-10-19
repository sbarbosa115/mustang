import {zodResolver} from '@hookform/resolvers/zod';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {z} from 'zod';
import {bookAppointment} from '@/entities/appointment';
import {customersKeys, listCustomers} from '@/entities/customer';
import {listUsers, usersKeys} from '@/entities/user';
import {ApiError} from '@/shared/api';
import {formatDateTime, localToUtc} from '@/shared/lib/dates';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {Skeleton} from '@/shared/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {Textarea} from '@/shared/ui/textarea';
import {appointmentChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

export interface CustomerRef {
  id: string;
  name: string;
}

const KINDS = ['visit', 'test_drive', 'delivery'] as const;
const LENGTHS = [30, 45, 60, 90, 120, 180];
const DEFAULT_TIME_ZONE = 'America/New_York';
/** Refusals that belong under the field that caused them. */
const WHEN_ERRORS = [
  'appointment_overlap',
  'appointment_in_past',
  'invalid_scheduled_at',
];

interface Props {
  /** Book for this customer; without it the form searches for one. */
  customer?: CustomerRef;
  /** The rep who books is the rep who works the appointment, unless changed. */
  defaultUserId?: string;
  /** The dealership's timezone, in which "When" is typed. */
  timeZone?: string;
  onDone: () => void;
}

/** The booking form: customer, rep, when (dealership time), length, kind and a note. */
export function BookAppointmentForm({
  customer,
  defaultUserId = '',
  timeZone = DEFAULT_TIME_ZONE,
  onDone,
}: Props) {
  const {t, i18n} = useTranslation('appointments');
  const queryClient = useQueryClient();
  const [picked, setPicked] = useState<CustomerRef | null>(customer ?? null);
  const [term, setTerm] = useState('');
  const [missing, setMissing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const schema = z.object({
    user_id: z.string().min(1),
    scheduled_at: z
      .string()
      .min(1, t('book_dialog.when_required'))
      .refine((value) => localToUtc(value, timeZone) > new Date(), {
        message: t('book_dialog.when_past'),
      }),
    duration_minutes: z.string().min(1),
    kind: z.enum(KINDS),
    note: z.string().trim().max(2000),
  });
  type Values = z.infer<typeof schema>;

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      user_id: defaultUserId,
      scheduled_at: '',
      duration_minutes: '60',
      kind: 'visit',
      note: '',
    },
  });
  const {errors, isValid, isSubmitting} = form.formState;

  const search = {q: term.trim(), per_page: 6};
  const customers = useQuery({
    queryKey: customersKeys.list(search),
    queryFn: () => listCustomers(search),
    enabled: !picked && term.trim().length > 0,
  });
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
  });
  const reps = (users.data?.items ?? []).filter((u) => u.active);

  const onSubmit = form.handleSubmit(async (values) => {
    if (!picked) {
      setMissing(true);
      return;
    }
    setError(null);
    try {
      const appointment = await bookAppointment({
        customer_id: picked.id,
        user_id: values.user_id,
        deal_id: null,
        scheduled_at: values.scheduled_at,
        duration_minutes: Number(values.duration_minutes),
        kind: values.kind,
        note: values.note.trim() === '' ? null : values.note.trim(),
      });
      appointmentChanged(queryClient, appointment);
      toast.success(
        t('book_dialog.done', {
          name: appointment.customer.name,
          when: formatDateTime(appointment.scheduled_at, timeZone),
        }),
      );
      onDone();
    } catch (e) {
      if (e instanceof ApiError && WHEN_ERRORS.includes(e.code)) {
        form.setError('scheduled_at', {message: t(`error.${e.code}`)});
      } else if (e instanceof ApiError && e.code === 'user_not_found') {
        form.setError('user_id', {message: t('error.user_not_found')});
      } else {
        setError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  return (
    <form onSubmit={(e) => void onSubmit(e)} className="space-y-4" noValidate>
      {picked ? (
        <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/50 px-3 py-2 text-sm">
          <span className="font-medium">
            {t('book_dialog.chosen', {name: picked.name})}
          </span>
          {!customer && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => setPicked(null)}
            >
              {t('book_dialog.change_customer')}
            </Button>
          )}
        </div>
      ) : (
        <div className="space-y-2">
          <div className="space-y-1.5">
            <Label htmlFor="appointment_customer_search">
              {t('book_dialog.customer_search')}
            </Label>
            <Input
              id="appointment_customer_search"
              type="search"
              value={term}
              autoComplete="off"
              placeholder={t('book_dialog.customer_placeholder')}
              aria-invalid={missing || undefined}
              aria-describedby={
                missing ? 'appointment_customer_error' : undefined
              }
              onChange={(e) => {
                setTerm(e.target.value);
                setMissing(false);
              }}
            />
            {missing && (
              <p
                id="appointment_customer_error"
                role="alert"
                className="text-sm text-danger"
              >
                {t('book_dialog.customer_required')}
              </p>
            )}
          </div>
          {term.trim() !== '' &&
            (customers.isPending ? (
              <div
                className="space-y-2"
                aria-busy="true"
                aria-label={t('book_dialog.searching')}
              >
                <Skeleton className="h-11 w-full" />
                <Skeleton className="h-11 w-full" />
              </div>
            ) : customers.data && customers.data.items.length > 0 ? (
              <ul className="divide-y rounded-md border">
                {customers.data.items.map((c) => (
                  <li key={c.id}>
                    <button
                      type="button"
                      className="flex min-h-11 w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted"
                      aria-label={t('book_dialog.pick', {name: c.name})}
                      onClick={() => setPicked({id: c.id, name: c.name})}
                    >
                      <span className="font-medium">{c.name}</span>
                      <span className="text-muted-foreground">
                        {c.primary_phone ?? c.primary_email ?? ''}
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">
                {t('book_dialog.no_customers')}
              </p>
            ))}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="appointment_when">{t('book_dialog.when')}</Label>
          <Input
            id="appointment_when"
            type="datetime-local"
            aria-invalid={errors.scheduled_at ? true : undefined}
            aria-describedby="appointment_when_help"
            {...form.register('scheduled_at')}
          />
          <p
            id="appointment_when_help"
            className="text-xs text-muted-foreground"
          >
            {t('book_dialog.when_help', {zone: timeZone})}
          </p>
          {errors.scheduled_at?.message && (
            <p role="alert" className="text-sm text-danger">
              {errors.scheduled_at.message}
            </p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="appointment_rep">{t('book_dialog.rep')}</Label>
          <Controller
            control={form.control}
            name="user_id"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="appointment_rep" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {reps.map((u) => (
                    <SelectItem key={u.id} value={u.id}>
                      {u.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
          {errors.user_id?.message && (
            <p role="alert" className="text-sm text-danger">
              {errors.user_id.message}
            </p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="appointment_kind">{t('book_dialog.kind')}</Label>
          <Controller
            control={form.control}
            name="kind"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="appointment_kind" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {KINDS.map((k) => (
                    <SelectItem key={k} value={k}>
                      {t(`kind.${k}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="appointment_length">
            {t('book_dialog.duration')}
          </Label>
          <Controller
            control={form.control}
            name="duration_minutes"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="appointment_length" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {LENGTHS.map((m) => (
                    <SelectItem key={m} value={String(m)}>
                      {t('book_dialog.duration_option', {count: m})}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="appointment_note">{t('book_dialog.note')}</Label>
        <Textarea
          id="appointment_note"
          rows={3}
          maxLength={2000}
          placeholder={t('book_dialog.note_placeholder')}
          {...form.register('note')}
        />
      </div>
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" onClick={onDone}>
          {t('common:action.cancel')}
        </Button>
        <Button type="submit" disabled={!isValid || isSubmitting}>
          {t('book_dialog.submit')}
        </Button>
      </div>
    </form>
  );
}
