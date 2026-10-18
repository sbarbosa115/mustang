import {Flame} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import type {Conversation} from '@/entities/conversation';
import {cn} from '@/shared/lib/utils';
import {ChannelIcon, RelativeTime} from '@/shared/ui/mustang';

interface Props {
  conversation: Conversation;
  href: string;
  selected: boolean;
  timeZone?: string;
}

/** One conversation: who, the last message, when, unread and the unreplied / fumbled markers (with words). */
export function ConversationRow({
  conversation,
  href,
  selected,
  timeZone,
}: Props) {
  const {t} = useTranslation('inbox');
  const last = conversation.last_message;
  const unread = conversation.unread_count > 0;
  return (
    <Link
      to={href}
      aria-current={selected ? 'page' : undefined}
      className={cn(
        'block min-w-0 rounded-md px-3 py-2 text-card-foreground',
        selected ? 'bg-accent' : 'hover:bg-muted',
      )}
    >
      <span className="flex items-baseline justify-between gap-2">
        <span
          className={cn('truncate', unread ? 'font-semibold' : 'font-medium')}
        >
          {conversation.customer.name}
        </span>
        {conversation.last_message_at && (
          <RelativeTime
            iso={conversation.last_message_at}
            timeZone={timeZone}
            className="shrink-0 text-xs text-muted-foreground"
          />
        )}
      </span>
      <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
        {last && <ChannelIcon channel={last.channel} className="shrink-0" />}
        <span className="truncate">
          {last
            ? last.direction === 'out'
              ? t('list.you', {body: last.body})
              : last.body
            : t('list.no_messages')}
        </span>
      </span>
      <span className="mt-1 flex flex-wrap items-center gap-2 text-xs">
        {conversation.unreplied && (
          <span className="inline-flex items-center gap-1 rounded-full bg-hot-surface px-2 py-0.5 font-medium text-hot">
            <Flame aria-hidden className="size-3.5" />
            {conversation.fumbled ? t('list.fumbled') : t('list.unreplied')}
          </span>
        )}
        {unread && (
          <span className="tabular rounded-full bg-primary px-2 py-0.5 font-medium text-primary-foreground">
            {t('list.unread', {count: conversation.unread_count})}
          </span>
        )}
        <span className="text-muted-foreground">
          {conversation.assignee?.name ?? t('list.unassigned')}
        </span>
      </span>
    </Link>
  );
}
