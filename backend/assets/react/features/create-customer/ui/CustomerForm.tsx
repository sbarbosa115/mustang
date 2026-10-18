import {zodResolver} from '@hookform/resolvers/zod';
import {useQuery} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {z} from 'zod';
import {createCustomer, type Customer} from '@/entities/customer';
import {listUsers, useAuth, usersKeys} from '@/entities/user';
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

const NONE = 'none';

const schema = z
  .object({
    first_name: z.string().trim().min(1).max(80),
    last_name: z.string().trim().min(1).max(80),
    phone: z.string().trim().max(40),
    email: z.string().trim().max(180),
    preferred_channel: z.string(),
    sales1_user_id: z.string(),
  })
  .refine((v) => v.phone !== '' || v.email !== '', {
    path: ['phone'],
    message: 'contact',
  })
  .refine(
    (v) => v.email === '' || z.string().email().safeParse(v.email).success,
    {
      path: ['email'],
      message: 'email',
    },
  );
type Values = z.infer<typeof schema>;

interface Props {
  onCreated: (customer: Customer) => void;
  onCancel: () => void;
}

/** The "Add customer" form: a name and a phone or an email are enough; an existing contact is offered instead. */
export function CustomerForm({onCreated, onCancel}: Props) {
  const {t, i18n} = useTranslation('customers');
  const {me} = useAuth();
  const [duplicate, setDuplicate] = useState<{
    id: string;
    kind: 'phone' | 'email';
  } | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
  });
  const reps = (users.data?.items ?? []).filter(
    (u) => u.active && (u.role === 'sales' || u.role === 'manager'),
  );
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      first_name: '',
      last_name: '',
      phone: '',
      email: '',
      preferred_channel: NONE,
      sales1_user_id: me?.user.role === 'sales' ? me.user.id : NONE,
    },
  });
  const {errors, isValid, isSubmitting} = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setDuplicate(null);
    setFormError(null);
    try {
      const customer = await createCustomer({
        first_name: values.first_name.trim(),
        last_name: values.last_name.trim(),
        phone: values.phone.trim() || null,
        email: values.email.trim() || null,
        preferred_channel:
          values.preferred_channel === NONE
            ? null
            : (values.preferred_channel as 'text' | 'email' | 'call'),
        sales1_user_id:
          values.sales1_user_id === NONE ? null : values.sales1_user_id,
      });
      onCreated(customer);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'duplicate_contact') {
        setDuplicate({
          id: String(e.detail.customer_id ?? ''),
          kind: e.detail.kind === 'email' ? 'email' : 'phone',
        });
      } else if (e instanceof ApiError && e.code === 'invalid_phone') {
        form.setError('phone', {message: t('error.invalid_phone')});
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
    if (error === 'contact') return t('create.contact_hint');
    if (error === 'email') return t('error.invalid_email');
    return error;
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          id="first_name"
          label={t('create.first_name')}
          error={message('first_name')}
        >
          <Input
            id="first_name"
            autoComplete="given-name"
            autoFocus
            {...form.register('first_name')}
          />
        </Field>
        <Field
          id="last_name"
          label={t('create.last_name')}
          error={message('last_name')}
        >
          <Input
            id="last_name"
            autoComplete="family-name"
            {...form.register('last_name')}
          />
        </Field>
      </div>
      <Field id="phone" label={t('create.phone')} error={message('phone')}>
        <Input
          id="phone"
          type="tel"
          autoComplete="tel"
          inputMode="tel"
          {...form.register('phone')}
        />
      </Field>
      <Field id="email" label={t('create.email')} error={message('email')}>
        <Input
          id="email"
          type="email"
          autoComplete="email"
          {...form.register('email')}
        />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field id="preferred_channel" label={t('create.preferred_channel')}>
          <Controller
            control={form.control}
            name="preferred_channel"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="preferred_channel" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NONE}>
                    {t('create.preferred_none')}
                  </SelectItem>
                  <SelectItem value="text">
                    {t('common:channel.text')}
                  </SelectItem>
                  <SelectItem value="email">
                    {t('common:channel.email')}
                  </SelectItem>
                  <SelectItem value="call">
                    {t('common:channel.call')}
                  </SelectItem>
                </SelectContent>
              </Select>
            )}
          />
        </Field>
        <Field id="sales1_user_id" label={t('create.sales1')}>
          <Controller
            control={form.control}
            name="sales1_user_id"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="sales1_user_id" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NONE}>
                    {t('create.sales1_none')}
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
      </div>
      {duplicate && (
        <p role="alert" className="text-sm text-danger">
          {t(`create.duplicate_${duplicate.kind}`)}{' '}
          {duplicate.id && (
            <Link
              to={`/customers/${duplicate.id}`}
              className="font-medium underline"
            >
              {t('create.open_existing')}
            </Link>
          )}
        </p>
      )}
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
          {isSubmitting ? t('create.submitting') : t('create.submit')}
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
