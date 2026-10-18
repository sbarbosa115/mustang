import {useQueryClient} from '@tanstack/react-query';
import {AlertTriangle, Send} from 'lucide-react';
import {useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {conversationsKeys} from '@/entities/conversation';
import {customersKeys} from '@/entities/customer';
import {sendMessage, type Message} from '@/entities/message';
import {
  PLACEHOLDERS,
  renderTemplate,
  type TemplateValues,
} from '@/entities/template';
import {ApiError} from '@/shared/api';
import {cn} from '@/shared/lib/utils';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {
  ChannelIcon,
  ConsentIndicator,
  type Channel,
  type ChannelAvailability,
} from '@/shared/ui/mustang';
import {Textarea} from '@/shared/ui/textarea';
import {refreshesThread, sendErrorText} from '../lib/errors';
import {
  blockOf,
  initialChannel,
  SEND_CHANNELS,
  type Block,
  type Contacts,
  type SendChannel,
} from '../model/channel';

/** What a tool (a template, a vehicle) puts into the composer. With a template id, its placeholders are filled. */
export interface ComposerDraft {
  body: string;
  subject?: string | null;
  templateId?: string | null;
}

/** What the composer gives the tools slotted into it. */
export interface ComposerTools {
  channel: SendChannel;
  disabled: boolean;
  insert: (draft: ComposerDraft) => void;
}

interface Props {
  conversationId: string;
  customerId: string;
  consent: ChannelAvailability;
  contacts: Contacts;
  preferredChannel?: Channel | null;
  /** The values a template's placeholders are filled with. */
  values?: TemplateValues;
  /** Buttons next to Send: the template picker and the vehicle picker (features slot them in). */
  tools?: (tools: ComposerTools) => ReactNode;
  onSent?: (message: Message) => void;
}

/**
 * The reply box: channel, consent, subject for email, message and send. Consent shows before anything is sent, a
 * channel without it is disabled with its reason (DESIGN.md principle 5), and a refusal shows inline by its code.
 */
export function Composer({
  conversationId,
  customerId,
  consent,
  contacts,
  preferredChannel,
  values = {},
  tools,
  onSent,
}: Props) {
  const {t, i18n} = useTranslation('inbox');
  const queryClient = useQueryClient();
  const [channel, setChannel] = useState<SendChannel>(() =>
    initialChannel(consent, contacts, preferredChannel),
  );
  const [body, setBody] = useState('');
  const [subject, setSubject] = useState('');
  const [templateId, setTemplateId] = useState<string | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [sending, setSending] = useState(false);

  const blocks = Object.fromEntries(
    SEND_CHANNELS.map((c) => [c, blockOf(c, consent, contacts)]),
  ) as Record<SendChannel, Block | null>;
  const blocked = blocks[channel] !== null;
  const needsSubject = channel === 'email' && subject.trim() === '';
  const canSend =
    !blocked && !sending && body.trim() !== '' && !needsSubject;
  const unfilled = [
    ...new Set(
      [...`${subject} ${body}`.matchAll(/\{([a-z_]+)\}/g)]
        .map((m) => m[1] ?? '')
        .filter((name) => (PLACEHOLDERS as readonly string[]).includes(name)),
    ),
  ];
  const fieldError = (field: string) =>
    error instanceof ApiError ? error.fieldError(field) : undefined;

  const reasonOf = (c: SendChannel, block: Block): string =>
    block.kind === 'no_contact'
      ? t(`composer.no_contact.${c}`)
      : t(`common:channel.blocked.${block.state}`, {
          channel: t(`common:channel.${c}`),
        });

  function insert(draft: ComposerDraft) {
    setError(null);
    if (draft.templateId) {
      setBody(renderTemplate(draft.body, values));
      if (draft.subject) setSubject(renderTemplate(draft.subject, values));
      setTemplateId(draft.templateId);
      return;
    }
    setBody((current) =>
      current.trim() === '' ? draft.body : `${current.trimEnd()}\n\n${draft.body}`,
    );
    if (draft.subject && subject.trim() === '') setSubject(draft.subject);
  }

  function switchTo(next: SendChannel) {
    setChannel(next);
    setError(null);
    // A template belongs to one channel.
    setTemplateId(null);
  }

  async function submit() {
    if (!canSend) return;
    setError(null);
    setSending(true);
    try {
      const message = await sendMessage(conversationId, {
        channel,
        body: body.trim(),
        subject: channel === 'email' ? subject.trim() : null,
        template_id: templateId,
      });
      setBody('');
      setSubject('');
      setTemplateId(null);
      void queryClient.invalidateQueries({queryKey: conversationsKeys.all});
      onSent?.(message);
    } catch (e) {
      setError(e);
      if (refreshesThread(e)) {
        // The blocked attempt is kept in the thread, and consent may have changed since the page loaded.
        void queryClient.invalidateQueries({queryKey: conversationsKeys.all});
        void queryClient.invalidateQueries({
          queryKey: customersKeys.detail(customerId),
        });
      }
    } finally {
      setSending(false);
    }
  }

  const anyConsentBlock = SEND_CHANNELS.some(
    (c) => blocks[c]?.kind === 'consent',
  );
  const anyContactBlock = SEND_CHANNELS.some(
    (c) => blocks[c]?.kind === 'no_contact',
  );
  const generalError =
    error != null && !(fieldError('body') || fieldError('subject'))
      ? sendErrorText(t, (k) => i18n.exists(k), error)
      : null;

  return (
    <form
      aria-label={t('composer.label')}
      className="space-y-3 border-t bg-card p-3 sm:p-4"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        void submit();
      }}
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div
          role="group"
          aria-label={t('composer.channel')}
          className="flex gap-1"
        >
          {SEND_CHANNELS.map((c) => {
            const block = blocks[c];
            return (
              <Button
                key={c}
                type="button"
                size="sm"
                variant={channel === c ? 'secondary' : 'ghost'}
                aria-pressed={channel === c}
                disabled={block !== null}
                title={block ? reasonOf(c, block) : undefined}
                onClick={() => switchTo(c)}
              >
                <ChannelIcon channel={c} />
                {t(`common:channel.${c}`)}
              </Button>
            );
          })}
        </div>
        <ConsentIndicator availability={consent} channels={SEND_CHANNELS} />
      </div>

      {(anyConsentBlock || anyContactBlock) && (
        <div
          className={cn(
            'flex flex-wrap items-start gap-x-3 gap-y-1 rounded-md px-3 py-2 text-sm',
            blocked
              ? 'bg-warning-surface text-warning'
              : 'bg-muted text-muted-foreground',
          )}
        >
          <AlertTriangle aria-hidden className="mt-0.5 size-4 shrink-0" />
          <ul className="flex-1 space-y-0.5">
            {SEND_CHANNELS.map((c) => {
              const block = blocks[c];
              return block ? <li key={c}>{reasonOf(c, block)}</li> : null;
            })}
          </ul>
          <span className="flex gap-3">
            {anyConsentBlock && (
              <Link
                to={`/customers/${customerId}/consent`}
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                {t('composer.record_consent')}
              </Link>
            )}
            {anyContactBlock && (
              <Link
                to={`/customers/${customerId}`}
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                {t('composer.add_contact')}
              </Link>
            )}
          </span>
        </div>
      )}

      {channel === 'email' && (
        <div className="space-y-1">
          <Input
            aria-label={t('composer.subject')}
            placeholder={t('composer.subject')}
            value={subject}
            maxLength={255}
            disabled={blocked}
            aria-invalid={fieldError('subject') ? true : undefined}
            onChange={(event) => setSubject(event.target.value)}
          />
          {fieldError('subject') && (
            <p className="text-sm text-danger">{fieldError('subject')}</p>
          )}
        </div>
      )}

      <div className="space-y-1">
        <Textarea
          aria-label={t('composer.body')}
          placeholder={t('composer.body_placeholder')}
          value={body}
          rows={3}
          maxLength={5000}
          disabled={blocked}
          aria-invalid={fieldError('body') ? true : undefined}
          className="max-h-48 min-h-20 resize-y"
          onChange={(event) => {
            setBody(event.target.value);
            if (event.target.value.trim() === '') setTemplateId(null);
          }}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
              event.preventDefault();
              void submit();
            }
          }}
        />
        {fieldError('body') && (
          <p className="text-sm text-danger">{fieldError('body')}</p>
        )}
        {unfilled.length > 0 && (
          <p className="text-sm text-warning">
            {t('composer.unfilled', {
              placeholders: unfilled.map((p) => `{${p}}`).join(', '),
            })}
          </p>
        )}
        {!blocked && needsSubject && body.trim() !== '' && (
          <p className="text-sm text-muted-foreground">
            {t('composer.subject_needed')}
          </p>
        )}
      </div>

      {generalError && (
        <p role="alert" className="text-sm text-danger">
          {generalError}
        </p>
      )}

      <div className="flex items-center gap-2">
        {tools?.({channel, disabled: blocked, insert})}
        <span className="ml-auto hidden text-xs text-muted-foreground lg:inline">
          {t('composer.shortcut')}
        </span>
        <Button type="submit" disabled={!canSend} className="max-lg:ml-auto">
          <Send aria-hidden />
          {sending ? t('composer.sending') : t('composer.send')}
        </Button>
      </div>
    </form>
  );
}
