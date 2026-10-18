import {zodResolver} from '@hookform/resolvers/zod';
import {useState} from 'react';
import {Controller, useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {z} from 'zod';
import {createUser, type Role} from '@/entities/user';
import type {Schema} from '@/shared/api';
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

const ROLES: Role[] = ['sales', 'bdc', 'manager', 'admin'];

const schema = z.object({
  first_name: z.string().trim().min(1).max(80),
  last_name: z.string().trim().min(1).max(80),
  email: z.string().trim().max(180).email('email'),
  role: z.enum(['sales', 'bdc', 'manager', 'admin']),
  password: z.string().min(12, 'password').max(200),
});
type Values = z.infer<typeof schema>;

interface Props {
  onCreated: (user: Schema<'UserOutput'>) => void;
  onCancel: () => void;
}

/** The "Add user" form: name, email, role and a temporary password of at least 12 characters. */
export function UserForm({onCreated, onCancel}: Props) {
  const {t, i18n} = useTranslation('users');
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      first_name: '',
      last_name: '',
      email: '',
      role: 'sales',
      password: '',
    },
  });
  const {errors, isValid, isSubmitting} = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    try {
      onCreated(
        await createUser({
          first_name: values.first_name.trim(),
          last_name: values.last_name.trim(),
          email: values.email.trim(),
          role: values.role,
          password: values.password,
        }),
      );
    } catch (e) {
      if (e instanceof ApiError && e.code === 'email_taken') {
        form.setError('email', {message: t('error.email_taken')});
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
    if (error === 'email') return t('error.invalid_email');
    if (error === 'password') return t('error.password_short');
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
            autoComplete="off"
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
            autoComplete="off"
            {...form.register('last_name')}
          />
        </Field>
      </div>
      <Field id="email" label={t('create.email')} error={message('email')}>
        <Input
          id="email"
          type="email"
          autoComplete="off"
          {...form.register('email')}
        />
      </Field>
      <Field id="role" label={t('create.role')}>
        <Controller
          control={form.control}
          name="role"
          render={({field}) => (
            <Select value={field.value} onValueChange={field.onChange}>
              <SelectTrigger id="role" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {ROLES.map((role) => (
                  <SelectItem key={role} value={role}>
                    {t(`settings:role.${role}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        />
      </Field>
      <Field
        id="password"
        label={t('create.password')}
        error={message('password')}
        hint={t('create.password_hint')}
      >
        <Input
          id="password"
          type="password"
          autoComplete="new-password"
          {...form.register('password')}
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
  hint,
  children,
}: {
  id: string;
  label: string;
  error?: string | null;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {hint && !error && (
        <p className="text-sm text-muted-foreground">{hint}</p>
      )}
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
