import {zodResolver} from '@hookform/resolvers/zod';
import {useQuery} from '@tanstack/react-query';
import {useEffect, useState} from 'react';
import {Controller, useForm, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {z} from 'zod';
import {customersKeys, lookupCustomers} from '@/entities/customer';
import {captureLead, type Lead} from '@/entities/lead';
import {listUsers, usersKeys} from '@/entities/user';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {errorText} from '../lib/errors';
import {CAPTURE_SOURCES, type LeadSource} from '../lib/sources';

const ROUND_ROBIN = 'round_robin';
const LEAD_ROLES = ['sales', 'bdc', 'manager'];

const schema = z
  .object({
    source: z.string().min(1, 'source'),
    first_name: z.string().trim().max(80),
    last_name: z.string().trim().max(80),
    phone: z.string().trim().max(40),
    email: z.string().trim().max(180),
    vehicle_interest_text: z.string().trim().max(255),
    channel_note: z.string().trim().max(255),
    assigned_user_id: z.string(),
  })
  .refine((v) => v.phone !== '' || v.email !== '', {
    path: ['phone'],
    message: 'contact',
  })
  .refine(
    (v) => v.email === '' || z.string().email().safeParse(v.email).success,
    {path: ['email'], message: 'email'},
  );
type Values = z.infer<typeof schema>;

interface Props {
  onCaptured: (lead: Lead) => void;
  onCancel: () => void;
}

function useDebounced<T>(value: T, ms: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), ms);
    return () => clearTimeout(timer);
  }, [value, ms]);
  return debounced;
}

/**
 * "Capture lead": a source and a phone or an email are enough. While typing, the customer already on file (phone
 * first, then email) is named, so the rep knows no duplicate is made; a new customer needs a name.
 */
export function CaptureLeadForm({onCaptured, onCancel}: Props) {
  const {t, i18n} = useTranslation('leads');
  const [formError, setFormError] = useState<string | null>(null);
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
  });
  const reps = (users.data?.items ?? []).filter(
    (u) => u.active && LEAD_ROLES.includes(u.role),
  );
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      source: '',
      first_name: '',
      last_name: '',
      phone: '',
      email: '',
      vehicle_interest_text: '',
      channel_note: '',
      assigned_user_id: ROUND_ROBIN,
    },
  });
  const {errors, isValid, isSubmitting} = form.formState;

  const phone = useDebounced(
    useWatch({control: form.control, name: 'phone'}).trim(),
    400,
  );
  const email = useDebounced(
    useWatch({control: form.control, name: 'email'}).trim(),
    400,
  );
  const byPhone = useQuery({
    queryKey: customersKeys.list({lookup: 'phone', phone}),
    queryFn: () => lookupCustomers({phone}),
    enabled: phone.replace(/\D/g, '').length >= 10,
  });
  const byEmail = useQuery({
    queryKey: customersKeys.list({lookup: 'email', email}),
    queryFn: () => lookupCustomers({email}),
    enabled: z.string().email().safeParse(email).success,
  });
  const match =
    (phone !== '' ? byPhone.data?.matches[0] : undefined) ??
    (email !== '' ? byEmail.data?.matches[0] : undefined);

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    const text = (value: string) => value.trim() || null;
    try {
      const lead = await captureLead({
        source: values.source as LeadSource,
        customer_id: null,
        first_name: text(values.first_name),
        last_name: text(values.last_name),
        phone: text(values.phone),
        email: text(values.email),
        vehicle_interest_text: text(values.vehicle_interest_text),
        channel_note: text(values.channel_note),
        assigned_user_id:
          values.assigned_user_id === ROUND_ROBIN
            ? null
            : values.assigned_user_id,
        received_at: null,
      });
      onCaptured(lead);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'invalid_phone') {
        form.setError('phone', {message: t('error.invalid_phone')});
      } else if (e instanceof ApiError && e.code === 'invalid_email') {
        form.setError('email', {message: t('error.invalid_email')});
      } else if (e instanceof ApiError && e.code === 'customer_name_required') {
        form.setError('first_name', {
          message: t('error.customer_name_required'),
        });
      } else if (e instanceof ApiError && e.violations.length > 0) {
        for (const v of e.violations) {
          form.setError(v.field as keyof Values, {message: v.message});
        }
      } else {
        setFormError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  const message = (field: keyof Values) => {
    const error = errors[field]?.message;
    if (!error) return null;
    if (error === 'contact') return t('capture.contact_hint');
    if (error === 'email') return t('error.invalid_email');
    if (error === 'source') return null;
    return error;
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <Field id="lead_source" label={t('capture.source')}>
        <Controller
          control={form.control}
          name="source"
          render={({field}) => (
            <Select value={field.value} onValueChange={field.onChange}>
              <SelectTrigger id="lead_source" className="w-full">
                <SelectValue placeholder={t('capture.source_placeholder')} />
              </SelectTrigger>
              <SelectContent>
                {CAPTURE_SOURCES.map((source) => (
                  <SelectItem key={source} value={source}>
                    {t(`source.${source}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          id="lead_phone"
          label={t('capture.phone')}
          error={message('phone')}
        >
          <Input
            id="lead_phone"
            type="tel"
            autoComplete="off"
            inputMode="tel"
            autoFocus
            {...form.register('phone')}
          />
        </Field>
        <Field
          id="lead_email"
          label={t('capture.email')}
          error={message('email')}
        >
          <Input
            id="lead_email"
            type="email"
            autoComplete="off"
            {...form.register('email')}
          />
        </Field>
      </div>
      {match && (
        <p role="status" className="text-sm text-muted-foreground">
          {t('capture.match', {name: match.name})}
        </p>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          id="lead_first_name"
          label={t('capture.first_name')}
          error={message('first_name')}
        >
          <Input
            id="lead_first_name"
            autoComplete="off"
            {...form.register('first_name')}
          />
        </Field>
        <Field
          id="lead_last_name"
          label={t('capture.last_name')}
          error={message('last_name')}
        >
          <Input
            id="lead_last_name"
            autoComplete="off"
            {...form.register('last_name')}
          />
        </Field>
      </div>
      <Field
        id="lead_vehicle"
        label={t('capture.vehicle')}
        error={message('vehicle_interest_text')}
      >
        <Input
          id="lead_vehicle"
          placeholder={t('capture.vehicle_placeholder')}
          {...form.register('vehicle_interest_text')}
        />
      </Field>
      <Field
        id="lead_note"
        label={t('capture.note')}
        error={message('channel_note')}
      >
        <Input
          id="lead_note"
          placeholder={t('capture.note_placeholder')}
          {...form.register('channel_note')}
        />
      </Field>
      <Field
        id="lead_assignee"
        label={t('capture.assignee')}
        error={message('assigned_user_id')}
      >
        <Controller
          control={form.control}
          name="assigned_user_id"
          render={({field}) => (
            <Select value={field.value} onValueChange={field.onChange}>
              <SelectTrigger id="lead_assignee" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ROUND_ROBIN}>
                  {t('capture.assignee_round_robin')}
                </SelectItem>
                {reps.map((u) => (
                  <SelectItem key={u.id} value={u.id}>
                    {u.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        />
      </Field>
      {formError && (
        <p role="alert" className="text-sm text-danger">
          {formError}
        </p>
      )}
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('common:action.cancel')}
        </Button>
        <Button type="submit" disabled={!isValid || isSubmitting}>
          {isSubmitting ? t('capture.submitting') : t('capture.submit')}
        </Button>
      </div>
    </form>
  );
}

function Field({
  id,
  label,
  error,
  children,
}: {
  id: string;
  label: string;
  error?: string | null;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
