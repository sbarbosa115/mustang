import {zodResolver} from '@hookform/resolvers/zod';
import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {z} from 'zod';
import {consentsKeys, recordConsent} from '@/entities/consent';
import {customersKeys, type Customer} from '@/entities/customer';
import {ApiError} from '@/shared/api';
import {localToUtc} from '@/shared/lib/dates';
import {formatPhone} from '@/shared/lib/phone';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import type {Channel} from '@/shared/ui/mustang';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {Textarea} from '@/shared/ui/textarea';
import {contactsFor} from '../lib/contacts';
import {errorText} from '../lib/errors';

const CHANNELS: Channel[] = ['text', 'email', 'call'];
/** What a person records by hand; sms_keyword and import are written by Mustang itself. */
const SOURCES = ['verbal', 'written', 'website', 'other'] as const;
const CONTACT_ERRORS = [
  'contact_not_on_customer',
  'invalid_phone',
  'invalid_email',
];
const DATE_ERRORS = ['invalid_captured_at', 'captured_at_in_future'];

const schema = z.object({
  channel: z.enum(['text', 'email', 'call']),
  contact_value: z.string().min(1),
  source: z.enum(SOURCES),
  captured_where: z.string().trim().max(120),
  captured_at: z.string(),
  wording: z.string().trim().max(2000),
  campaign: z.string().trim().max(120),
});
type Values = z.infer<typeof schema>;

interface Props {
  customer: Customer;
  /** The dealership's timezone, in which "When" is typed. */
  timeZone?: string;
  onDone: () => void;
}

/** Records consent for one of the customer's contacts on one channel, with how, where and when it was given. */
export function ConsentForm({customer, timeZone, onDone}: Props) {
  const {t, i18n} = useTranslation('consent');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const firstChannel: Channel = customer.preferred_channel ?? 'text';
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      channel: firstChannel,
      contact_value: contactsFor(customer, firstChannel)[0] ?? '',
      source: 'verbal',
      captured_where: '',
      captured_at: '',
      wording: '',
      campaign: '',
    },
  });
  const {isValid, isSubmitting, errors} = form.formState;
  const channel = useWatch({control: form.control, name: 'channel'});
  const options = contactsFor(customer, channel);
  const show = (value: string) =>
    channel === 'email' ? value : formatPhone(value);

  const onSubmit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      await recordConsent(customer.id, {
        channel: values.channel,
        contact_value: values.contact_value,
        source: values.source,
        captured_where: values.captured_where.trim() || null,
        captured_at: values.captured_at
          ? localToUtc(values.captured_at, timeZone).toISOString()
          : null,
        wording: values.wording.trim() || null,
        campaign: values.campaign.trim() || null,
      });
      void queryClient.invalidateQueries({queryKey: consentsKeys.all});
      void queryClient.invalidateQueries({
        queryKey: customersKeys.detail(customer.id),
      });
      toast.success(
        t('record.done', {
          channel: t(`common:channel.${values.channel}`),
          contact: show(values.contact_value),
        }),
      );
      onDone();
    } catch (e) {
      if (e instanceof ApiError && CONTACT_ERRORS.includes(e.code)) {
        form.setError('contact_value', {message: t(`error.${e.code}`)});
      } else if (e instanceof ApiError && DATE_ERRORS.includes(e.code)) {
        form.setError('captured_at', {message: t(`error.${e.code}`)});
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
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="consent_channel">{t('record.channel')}</Label>
          <Controller
            control={form.control}
            name="channel"
            render={({field}) => (
              <Select
                value={field.value}
                onValueChange={(next) => {
                  field.onChange(next);
                  form.setValue(
                    'contact_value',
                    contactsFor(customer, next as Channel)[0] ?? '',
                    {shouldValidate: true},
                  );
                }}
              >
                <SelectTrigger id="consent_channel" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CHANNELS.map((c) => (
                    <SelectItem key={c} value={c}>
                      {t(`common:channel.${c}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="consent_contact">{t('record.contact')}</Label>
          {options.length === 0 ? (
            <p id="consent_contact" className="text-sm text-muted-foreground">
              {t(
                channel === 'email'
                  ? 'record.no_contact_email'
                  : 'record.no_contact_phone',
              )}
            </p>
          ) : (
            <Controller
              control={form.control}
              name="contact_value"
              render={({field}) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger id="consent_contact" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {options.map((value) => (
                      <SelectItem key={value} value={value}>
                        {show(value)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            />
          )}
          {errors.contact_value?.message && (
            <p role="alert" className="text-sm text-danger">
              {errors.contact_value.message}
            </p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="consent_source">{t('record.source')}</Label>
          <Controller
            control={form.control}
            name="source"
            render={({field}) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="consent_source" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {SOURCES.map((s) => (
                    <SelectItem key={s} value={s}>
                      {t(`source.${s}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="consent_captured_at">{t('record.captured_at')}</Label>
          <Input
            id="consent_captured_at"
            type="datetime-local"
            aria-describedby="consent_captured_at_help"
            {...form.register('captured_at')}
          />
          <p
            id="consent_captured_at_help"
            className="text-xs text-muted-foreground"
          >
            {t('record.captured_at_help')}
          </p>
          {errors.captured_at?.message && (
            <p role="alert" className="text-sm text-danger">
              {errors.captured_at.message}
            </p>
          )}
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="consent_where">{t('record.where')}</Label>
        <Input
          id="consent_where"
          placeholder={t('record.where_placeholder')}
          {...form.register('captured_where')}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="consent_wording">{t('record.wording')}</Label>
        <Textarea
          id="consent_wording"
          rows={3}
          placeholder={t('record.wording_placeholder')}
          {...form.register('wording')}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="consent_campaign">{t('record.campaign')}</Label>
        <Input
          id="consent_campaign"
          placeholder={t('record.campaign_placeholder')}
          {...form.register('campaign')}
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
          {t('record.submit')}
        </Button>
      </div>
    </form>
  );
}
