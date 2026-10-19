import {formatInTimeZone} from 'date-fns-tz';
import type {Message} from '@/entities/message';

export interface DayGroup {
  /** yyyy-MM-dd in the dealership's timezone. */
  day: string;
  messages: Message[];
}

/** When a message happened: received for inbound, sent (or attempted) for outbound. */
export function messageTime(message: Message): string {
  return message.direction === 'in'
    ? (message.received_at ?? message.created_at)
    : (message.sent_at ?? message.created_at);
}

/** Messages (oldest first, as the API gives them) grouped by their day in the dealership's timezone. */
export function groupByDay(messages: Message[], timeZone: string): DayGroup[] {
  const groups: DayGroup[] = [];
  for (const message of messages) {
    const day = formatInTimeZone(
      new Date(messageTime(message)),
      timeZone,
      'yyyy-MM-dd',
    );
    const last = groups.at(-1);
    if (last && last.day === day) last.messages.push(message);
    else groups.push({day, messages: [message]});
  }
  return groups;
}

/** "today", "yesterday", or the date ("Friday, Oct 9"; with the year when it is not this year). */
export function dayLabel(
  day: string,
  timeZone: string,
  now: Date = new Date(),
): {kind: 'today' | 'yesterday'} | {kind: 'date'; text: string} {
  const today = formatInTimeZone(now, timeZone, 'yyyy-MM-dd');
  const yesterday = formatInTimeZone(
    new Date(now.getTime() - 24 * 60 * 60 * 1000),
    timeZone,
    'yyyy-MM-dd',
  );
  if (day === today) return {kind: 'today'};
  if (day === yesterday) return {kind: 'yesterday'};
  // Noon UTC of that calendar day falls on the same day in every US timezone.
  const at = new Date(`${day}T12:00:00Z`);
  const sameYear = day.slice(0, 4) === today.slice(0, 4);
  return {
    kind: 'date',
    text: formatInTimeZone(at, 'UTC', sameYear ? 'EEEE, MMM d' : 'MMM d, yyyy'),
  };
}
