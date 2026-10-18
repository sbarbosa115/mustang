import {AlertCircle, Clock} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {cn} from '../../lib/utils';
import {ChannelIcon} from './ChannelIcon';
import {RelativeTime} from './RelativeTime';

interface Props {
  direction: 'in' | 'out';
  channel: 'text' | 'email' | 'call';
  body: string;
  subject?: string | null;
  at: string;
  status?: 'queued' | 'sent' | 'failed' | 'received' | 'blocked';
  authorName?: string | null;
  timeZone?: string;
}

/** Inbound on the left in muted, outbound on the right in primary, with channel and time below (DESIGN.md). */
export function MessageBubble({
  direction,
  channel,
  body,
  subject,
  at,
  status,
  authorName,
  timeZone,
}: Props) {
  const {t} = useTranslation('common');
  const outbound = direction === 'out';
  return (
    <div
      className={cn(
        'flex flex-col gap-1',
        outbound ? 'items-end' : 'items-start',
      )}
    >
      <div
        className={cn(
          'max-w-[85%] rounded-xl px-3 py-2 whitespace-pre-wrap break-words sm:max-w-[70%]',
          outbound
            ? 'bg-primary text-primary-foreground'
            : 'bg-muted text-foreground',
          status === 'failed' && 'ring-2 ring-danger',
        )}
      >
        {subject && <p className="mb-1 font-semibold">{subject}</p>}
        {body}
      </div>
      <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <ChannelIcon channel={channel} className="size-3.5" />
        <span>{t(`channel.${channel}`)}</span>
        <span aria-hidden>·</span>
        <RelativeTime iso={at} timeZone={timeZone} />
        {authorName && (
          <>
            <span aria-hidden>·</span>
            <span>{authorName}</span>
          </>
        )}
        {status === 'queued' && (
          <span className="inline-flex items-center gap-1">
            <Clock aria-hidden className="size-3.5" />
            {t('message.status.queued')}
          </span>
        )}
        {status === 'failed' && (
          <span className="inline-flex items-center gap-1 text-danger">
            <AlertCircle aria-hidden className="size-3.5" />
            {t('message.status.failed')}
          </span>
        )}
      </p>
    </div>
  );
}
