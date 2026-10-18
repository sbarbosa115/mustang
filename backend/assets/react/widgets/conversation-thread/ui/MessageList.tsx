import {Ban} from 'lucide-react';
import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import type {Message} from '@/entities/message';
import {MessageBubble} from '@/shared/ui/mustang';
import {dayLabel, groupByDay, messageTime} from '../lib/days';

interface Props {
  messages: Message[];
  timeZone: string;
}

const BLOCK_REASONS = ['consent_missing', 'opted_out'];

/** The thread, oldest first, grouped by day; it scrolls to the newest message. */
export function MessageList({messages, timeZone}: Props) {
  const {t} = useTranslation('inbox');
  const end = useRef<HTMLDivElement>(null);
  const last = messages.at(-1)?.id;

  useEffect(() => {
    end.current?.scrollIntoView?.({block: 'end'});
  }, [last]);

  if (messages.length === 0) {
    return (
      <p className="p-6 text-center text-sm text-muted-foreground">
        {t('thread.empty')}
      </p>
    );
  }

  return (
    <div className="space-y-4 p-3 sm:p-4">
      <ol aria-label={t('thread.messages')} className="space-y-4">
        {groupByDay(messages, timeZone).map((group) => {
          const label = dayLabel(group.day, timeZone);
          const text =
            label.kind === 'date' ? label.text : t(`thread.${label.kind}`);
          return (
            <li key={group.day} aria-label={text} className="space-y-3">
              <h3 className="text-center text-xs font-medium text-muted-foreground">
                {text}
              </h3>
              <ol className="space-y-3">
                {group.messages.map((message) => (
                  <li key={message.id} data-direction={message.direction}>
                    <MessageBubble
                      direction={message.direction}
                      channel={message.channel}
                      subject={message.subject}
                      body={message.body}
                      at={messageTime(message)}
                      status={message.status}
                      authorName={message.sent_by?.name}
                      timeZone={timeZone}
                    />
                    {message.status === 'blocked' && (
                      <p className="mt-1 flex items-center justify-end gap-1 text-xs text-danger">
                        <Ban aria-hidden className="size-3.5" />
                        {t('thread.blocked', {
                          reason: t(
                            `thread.blocked_reason.${
                              BLOCK_REASONS.includes(
                                message.failed_reason ?? '',
                              )
                                ? message.failed_reason
                                : 'unknown'
                            }`,
                          ),
                        })}
                      </p>
                    )}
                  </li>
                ))}
              </ol>
            </li>
          );
        })}
      </ol>
      <div ref={end} />
    </div>
  );
}
