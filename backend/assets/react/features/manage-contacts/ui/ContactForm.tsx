import {zodResolver} from '@hookform/resolvers/zod';
import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {z} from 'zod';
import {
  addContact,
  customersKeys,
  updateContact,
  type Customer,
} from '@/entities/customer';
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
import {Switch} from '@/shared/ui/switch';
import {errorText} from '../lib/errors';

const schema = z.object({
  kind: z.enum(['phone', 'email']),
  value: z.string().trim().min(1).max(180),
  label: z.string().trim().max(40),
  is_primary: z.boolean(),
});
type Values = z.infer<typeof schema>;

type Contact = Customer['contacts'][number];

interface Props {
  customer: Customer;
  /** The contact being edited; absent when adding. */
  contact?: Contact;
  onDone: () => void;
}

/** Adds a phone or email, or edits one (its type is fixed once saved). The API normalises and de-duplicates. */
export function ContactForm({customer, contact, onDone}: Props) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      kind: contact?.kind ?? 'phone',
      value: contact?.value ?? '',
      label: contact?.label ?? '',
      is_primary: contact?.is_primary ?? false,
    },
  });
  const {isValid, isSubmitting, errors} = form.formState;
  const kind = useWatch({control: form.control, name: 'kind'});

  const onSubmit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      const saved = contact
        ? await updateContact(customer.id, contact.id, {
            value: values.value.trim(),
            label: values.label.trim() || null,
            is_primary: values.is_primary,
          })
        : await addContact(customer.id, {
            kind: values.kind,
            value: values.value.trim(),
            label: values.label.trim() || null,
            is_primary: values.is_primary,
          });
      queryClient.setQueryData(customersKeys.detail(customer.id), saved);
      void queryClient.invalidateQueries({queryKey: ['customers', 'list']});
      toast.success(t('contacts.saved'));
      onDone();
    } catch (e) {
      if (
        e instanceof ApiError &&
        ['invalid_phone', 'invalid_email', 'duplicate_contact'].includes(e.code)
      ) {
        form.setError('value', {message: t(`error.${e.code}`)});
      } else if (e instanceof ApiError && e.violations.length > 0) {
        for (const v of e.violations) {
          form.setError(v.field as keyof Values, {message: v.message});
        }
      } else {
        setError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  return (
    <form
      onSubmit={onSubmit}
      className="space-y-3 rounded-md border p-3"
      noValidate
    >
      <h3 className="text-sm font-semibold">
        {contact ? t('contacts.edit_title') : t('contacts.add_title')}
      </h3>
      {!contact && (
        <div className="space-y-1.5">
          <Label htmlFor="contact_kind">{t('contacts.kind')}</Label>
          <Controller
            control={form.control}
            name="kind"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="contact_kind" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="phone">
                    {t('contacts.kind_phone')}
                  </SelectItem>
                  <SelectItem value="email">
                    {t('contacts.kind_email')}
                  </SelectItem>
                </SelectContent>
              </Select>
            )}
          />
        </div>
      )}
      <div className="space-y-1.5">
        <Label htmlFor="contact_value">{t('contacts.value')}</Label>
        <Input
          id="contact_value"
          type={kind === 'email' ? 'email' : 'tel'}
          inputMode={kind === 'email' ? 'email' : 'tel'}
          autoFocus
          {...form.register('value')}
        />
        {errors.value && (
          <p role="alert" className="text-sm text-danger">
            {errors.value.message}
          </p>
        )}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="contact_label">{t('contacts.label')}</Label>
        <Input
          id="contact_label"
          placeholder={t('contacts.label_placeholder')}
          {...form.register('label')}
        />
      </div>
      <div className="flex items-center gap-2">
        <Controller
          control={form.control}
          name="is_primary"
          render={({field}) => (
            <Switch
              id="contact_primary"
              checked={field.value}
              onCheckedChange={field.onChange}
            />
          )}
        />
        <Label htmlFor="contact_primary">
          {t('contacts.primary_toggle', {
            kind: t(`contacts.kind_${kind}`).toLowerCase(),
          })}
        </Label>
      </div>
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onDone}>
          {t('common:action.cancel')}
        </Button>
        <Button type="submit" disabled={!isValid || isSubmitting}>
          {t('common:action.save')}
        </Button>
      </div>
    </form>
  );
}
