import {Flame} from 'lucide-react';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {cn} from '../../lib/utils';
import {RelativeTime} from './RelativeTime';

interface Props {
  customerName: string;
  customerHref: string;
  /** The reason to act now, already worded ("Asked about a Camry 47 min ago; trade question unanswered"). */
  reason: string;
  hot?: boolean;
  since?: string | null;
  /** Usually <ChannelActions>; and a stage badge or appointment chip. */
  actions?: ReactNode;
  meta?: ReactNode;
  timeZone?: string;
}

/**
 * One row of the "contact now" list: who, why now, and one-tap actions (FR-03, FR-18). `hot` is the only warm
 * accent in the app and always comes with its word, never colour alone.
 */
export function PriorityItem({
  customerName,
  customerHref,
  reason,
  hot = false,
  since,
  actions,
  meta,
  timeZone,
}: Props) {
  const {t} = useTranslation('common');
  return (
    <article
      className={cn(
        'flex flex-col gap-3 rounded-lg border bg-card p-4 sm:flex-row sm:items-center',
        hot && 'border-l-4 border-l-hot',
      )}
    >
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          <Link to={customerHref} className="font-semibold hover:underline">
            {customerName}
          </Link>
          {hot && (
            <span className="inline-flex items-center gap-1 rounded-full bg-hot-surface px-2 py-0.5 text-xs font-medium text-hot">
              <Flame aria-hidden className="size-3.5" />
              {t('priority.hot')}
            </span>
          )}
          {meta}
        </div>
        <p className="text-sm">{reason}</p>
        {since && (
          <p className="text-xs text-muted-foreground">
            <RelativeTime iso={since} timeZone={timeZone} />
          </p>
        )}
      </div>
      {actions && <div className="shrink-0">{actions}</div>}
    </article>
  );
}
