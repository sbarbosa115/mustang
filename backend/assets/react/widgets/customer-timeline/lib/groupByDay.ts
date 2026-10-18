import {formatInTimeZone} from 'date-fns-tz';

export interface DayGroup<T> {
  /** yyyy-MM-dd in the dealership's timezone. */
  day: string;
  items: T[];
}

/** Entries (already newest first) grouped by the day they happened in the dealership's timezone. */
export function groupByDay<T extends {occurred_at: string}>(
  items: T[],
  timeZone = 'America/New_York',
): DayGroup<T>[] {
  const groups: DayGroup<T>[] = [];
  for (const item of items) {
    const day = formatInTimeZone(
      new Date(item.occurred_at),
      timeZone,
      'yyyy-MM-dd',
    );
    const last = groups[groups.length - 1];
    if (last && last.day === day) last.items.push(item);
    else groups.push({day, items: [item]});
  }
  return groups;
}

/** "today", "yesterday" or the date, e.g. "Mon, Oct 12", for a group heading. */
export function dayLabel(
  day: string,
  now: Date = new Date(),
  timeZone = 'America/New_York',
): {kind: 'today' | 'yesterday'} | {kind: 'date'; text: string} {
  const today = formatInTimeZone(now, timeZone, 'yyyy-MM-dd');
  const yesterday = formatInTimeZone(
    new Date(now.getTime() - 24 * 60 * 60_000),
    timeZone,
    'yyyy-MM-dd',
  );
  if (day === today) return {kind: 'today'};
  if (day === yesterday) return {kind: 'yesterday'};
  const noon = new Date(`${day}T12:00:00Z`);
  return {
    kind: 'date',
    text: formatInTimeZone(noon, 'UTC', 'EEE, MMM d, yyyy'),
  };
}
