import {zodResolver} from '@hookform/resolvers/zod';
import {useRef, useState} from 'react';
import {Controller, useForm, useWatch} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {z} from 'zod';
import {
  PLACEHOLDERS,
  createTemplate,
  renderTemplate,
  unknownPlaceholders,
  updateTemplate,
  type Placeholder,
  type Template,
  type TemplateValues,
} from '@/entities/template';
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
import {Textarea} from '@/shared/ui/textarea';
import {errorText} from '../lib/errors';

const CHANNELS = ['text', 'email'] as const;

const schema = z
  .object({
    name: z.string().trim().min(1, 'required').max(120),
    channel: z.enum(CHANNELS),
    subject: z.string().trim().max(255),
    body: z.string().trim().min(1, 'required').max(5000),
    active: z.boolean(),
  })
  .refine((v) => v.channel !== 'email' || v.subject !== '', {
    path: ['subject'],
    message: 'subject',
  })
  .refine((v) => unknownPlaceholders(v.body).length === 0, {
    path: ['body'],
    message: 'unknown',
  })
  .refine((v) => unknownPlaceholders(v.subject).length === 0, {
    path: ['subject'],
    message: 'unknown',
  });
type Values = z.infer<typeof schema>;

interface Props {
  /** Given when editing: its channel is then fixed. */
  template?: Template;
  onSaved: (template: Template) => void;
  onCancel: () => void;
}

/**
 * Create or edit a text or email template. The placeholder buttons add `{first_name}`-style tags at the caret, the
 * preview fills them with sample details through the same `renderTemplate` the inbox uses, and an unknown
 * placeholder is named and blocks saving (the API refuses it too).
 */
export function TemplateForm({template, onSaved, onCancel}: Props) {
  const {t, i18n} = useTranslation('templates');
  const [formError, setFormError] = useState<string | null>(null);
  const bodyRef = useRef<HTMLTextAreaElement | null>(null);
  const editing = template !== undefined;
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      name: template?.name ?? '',
      channel: (template?.channel === 'email' ? 'email' : 'text') as
        'text' | 'email',
      subject: template?.subject ?? '',
      body: template?.body ?? '',
      active: template?.active ?? true,
    },
  });
  const {errors, isValid, isSubmitting} = form.formState;
  const [channel, body, subject] = useWatch({
    control: form.control,
    name: ['channel', 'body', 'subject'],
  });
  const samples = Object.fromEntries(
    PLACEHOLDERS.map((name) => [name, t(`sample.${name}`)]),
  ) as TemplateValues;
  const bodyRegister = form.register('body');

  function insert(name: Placeholder) {
    const field = bodyRef.current;
    const tag = `{${name}}`;
    const current = form.getValues('body');
    const start = field?.selectionStart ?? current.length;
    const end = field?.selectionEnd ?? current.length;
    form.setValue('body', current.slice(0, start) + tag + current.slice(end), {
      shouldDirty: true,
      shouldValidate: true,
    });
    const caret = start + tag.length;
    requestAnimationFrame(() => {
      field?.focus();
      field?.setSelectionRange(caret, caret);
    });
  }

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null);
    const text = values.channel === 'email' ? values.subject.trim() : null;
    try {
      const saved = editing
        ? await updateTemplate(template.id, {
            name: values.name.trim(),
            subject: text,
            body: values.body,
            active: values.active,
          })
        : await createTemplate({
            name: values.name.trim(),
            channel: values.channel,
            subject: text,
            body: values.body,
            active: values.active,
          });
      onSaved(saved);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'unknown_placeholder') {
        const field = e.detail.field === 'subject' ? 'subject' : 'body';
        const names = Array.isArray(e.detail.placeholders)
          ? (e.detail.placeholders as string[])
          : [];
        form.setError(field, {message: unknownText(names)});
      } else if (e instanceof ApiError && e.code === 'subject_required') {
        form.setError('subject', {message: t('error.subject_required')});
      } else if (e instanceof ApiError && e.violations.length > 0) {
        for (const v of e.violations) {
          form.setError(v.field as keyof Values, {message: v.message});
        }
      } else {
        setFormError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  const unknownText = (names: string[]) =>
    t('form.unknown_placeholder', {
      count: names.length,
      names: names.map((n) => `{${n}}`).join(', '),
    });

  const message = (field: keyof Values, text: string) => {
    const error = errors[field]?.message;
    if (!error || error === 'required') return null;
    if (error === 'subject') return t('error.subject_required');
    if (error === 'unknown') return unknownText(unknownPlaceholders(text));
    return error;
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-[1fr_12rem]">
        <Field id="template_name" label={t('form.name')}>
          <Input
            id="template_name"
            autoComplete="off"
            autoFocus
            placeholder={t('form.name_placeholder')}
            {...form.register('name')}
          />
        </Field>
        <Field id="template_channel" label={t('form.channel')}>
          <Controller
            control={form.control}
            name="channel"
            render={({field}) => (
              <Select
                value={field.value}
                onValueChange={field.onChange}
                disabled={editing}
              >
                <SelectTrigger id="template_channel" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CHANNELS.map((c) => (
                    <SelectItem key={c} value={c}>
                      {t(`channel.${c}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </Field>
      </div>
      {channel === 'email' && (
        <Field
          id="template_subject"
          label={t('form.subject')}
          error={message('subject', subject)}
        >
          <Input
            id="template_subject"
            autoComplete="off"
            {...form.register('subject')}
          />
        </Field>
      )}
      <Field
        id="template_body"
        label={t('form.body')}
        error={message('body', body)}
      >
        <Textarea
          id="template_body"
          rows={5}
          {...bodyRegister}
          ref={(element) => {
            bodyRegister.ref(element);
            bodyRef.current = element;
          }}
        />
      </Field>
      <div className="space-y-1.5">
        <p className="text-sm font-medium">{t('form.placeholders')}</p>
        <p className="text-xs text-muted-foreground">
          {t('form.placeholders_hint')}
        </p>
        <ul className="flex flex-wrap gap-2">
          {PLACEHOLDERS.map((name) => (
            <li key={name}>
              <Button
                type="button"
                variant="outline"
                size="sm"
                title={t(`placeholder.${name}`)}
                aria-label={t('form.insert', {name: `{${name}}`})}
                onClick={() => insert(name)}
              >
                {`{${name}}`}
              </Button>
            </li>
          ))}
        </ul>
      </div>
      <section
        aria-label={t('form.preview')}
        className="space-y-1 rounded-lg border bg-muted/40 p-3"
      >
        <h3 className="text-sm font-medium">{t('form.preview')}</h3>
        <p className="text-xs text-muted-foreground">
          {t('form.preview_hint')}
        </p>
        {channel === 'email' && subject.trim() !== '' && (
          <p className="text-sm font-medium">
            {renderTemplate(subject, samples)}
          </p>
        )}
        <p className="text-sm whitespace-pre-wrap">
          {renderTemplate(body, samples)}
        </p>
      </section>
      <div className="flex items-start justify-between gap-4">
        <div className="space-y-0.5">
          <Label htmlFor="template_active">{t('form.active')}</Label>
          <p className="text-xs text-muted-foreground">
            {t('form.active_hint')}
          </p>
        </div>
        <Controller
          control={form.control}
          name="active"
          render={({field}) => (
            <Switch
              id="template_active"
              checked={field.value}
              onCheckedChange={field.onChange}
            />
          )}
        />
      </div>
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
          {isSubmitting ? t('form.submitting') : t('form.submit')}
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
