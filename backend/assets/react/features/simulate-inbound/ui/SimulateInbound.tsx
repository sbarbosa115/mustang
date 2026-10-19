import {useQueryClient} from '@tanstack/react-query';
import {FlaskConical} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  conversationsKeys,
  simulateInbound,
  type DevInbound,
} from '@/entities/conversation';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {ChannelIcon} from '@/shared/ui/mustang';
import {Textarea} from '@/shared/ui/textarea';
import {useDevInboundAvailable} from '../model/available';

type InboundChannel = 'text' | 'email';

interface Props {
  /** The customer's phone and email, to prefill "From" (in a thread). */
  contacts?: {phone?: string | null; email?: string | null};
  onDone?: (result: DevInbound) => void;
}

/**
 * Dev and test only: a customer's reply arrives as if they wrote in. Shown only when the API exposes
 * POST /api/v1/dev/inbound.
 */
export function SimulateInbound({contacts, onDone}: Props) {
  const {t} = useTranslation('inbox');
  const available = useDevInboundAvailable();
  const [open, setOpen] = useState(false);
  if (!available) return null;
  return (
    <>
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() => setOpen(true)}
      >
        <FlaskConical aria-hidden />
        {t('simulate.open')}
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          {open && (
            <SimulateForm
              contacts={contacts}
              onDone={(result) => {
                setOpen(false);
                onDone?.(result);
              }}
              onCancel={() => setOpen(false)}
            />
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}

function SimulateForm({
  contacts,
  onDone,
  onCancel,
}: {
  contacts?: Props['contacts'];
  onDone: (result: DevInbound) => void;
  onCancel: () => void;
}) {
  const {t, i18n} = useTranslation('inbox');
  const queryClient = useQueryClient();
  const contactOf = (c: InboundChannel) =>
    (c === 'text' ? contacts?.phone : contacts?.email) ?? '';
  const [channel, setChannel] = useState<InboundChannel>(
    contacts?.phone || !contacts?.email ? 'text' : 'email',
  );
  const [from, setFrom] = useState(contactOf(channel));
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [error, setError] = useState<unknown>(null);
  const [saving, setSaving] = useState(false);

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSaving(true);
    try {
      const result = await simulateInbound({
        from: from.trim(),
        channel,
        body: body.trim(),
        subject: channel === 'email' && subject.trim() ? subject.trim() : null,
      });
      void queryClient.invalidateQueries({queryKey: conversationsKeys.all});
      onDone(result);
    } catch (e) {
      setError(e);
    } finally {
      setSaving(false);
    }
  }

  const code = error instanceof ApiError ? error.code : '';
  const fieldError = (field: string) =>
    error instanceof ApiError ? error.fieldError(field) : undefined;
  const message =
    error == null || fieldError('from') || fieldError('body')
      ? null
      : i18n.exists(`inbox:simulate.error.${code}`)
        ? t(`simulate.error.${code}`)
        : t('common:error.generic');

  return (
    <form onSubmit={(e) => void submit(e)} className="space-y-4" noValidate>
      <DialogHeader>
        <DialogTitle>{t('simulate.title')}</DialogTitle>
        <DialogDescription>{t('simulate.description')}</DialogDescription>
      </DialogHeader>
      <div
        role="group"
        aria-label={t('simulate.channel')}
        className="flex gap-1"
      >
        {(['text', 'email'] as const).map((c) => (
          <Button
            key={c}
            type="button"
            size="sm"
            variant={channel === c ? 'secondary' : 'ghost'}
            aria-pressed={channel === c}
            onClick={() => {
              setChannel(c);
              setFrom(contactOf(c));
            }}
          >
            <ChannelIcon channel={c} />
            {t(`common:channel.${c}`)}
          </Button>
        ))}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="simulate_from">{t('simulate.from')}</Label>
        <Input
          id="simulate_from"
          value={from}
          maxLength={180}
          placeholder={t('simulate.from_placeholder')}
          onChange={(e) => setFrom(e.target.value)}
        />
        {fieldError('from') && (
          <p className="text-sm text-danger">{fieldError('from')}</p>
        )}
      </div>
      {channel === 'email' && (
        <div className="space-y-1.5">
          <Label htmlFor="simulate_subject">{t('simulate.subject')}</Label>
          <Input
            id="simulate_subject"
            value={subject}
            maxLength={255}
            onChange={(e) => setSubject(e.target.value)}
          />
        </div>
      )}
      <div className="space-y-1.5">
        <Label htmlFor="simulate_body">{t('simulate.body')}</Label>
        <Textarea
          id="simulate_body"
          value={body}
          rows={3}
          maxLength={5000}
          onChange={(e) => setBody(e.target.value)}
        />
        {fieldError('body') && (
          <p className="text-sm text-danger">{fieldError('body')}</p>
        )}
      </div>
      {message && (
        <p role="alert" className="text-sm text-danger">
          {message}
        </p>
      )}
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('common:action.cancel')}
        </Button>
        <Button
          type="submit"
          disabled={saving || from.trim() === '' || body.trim() === ''}
        >
          {t('simulate.submit')}
        </Button>
      </DialogFooter>
    </form>
  );
}
