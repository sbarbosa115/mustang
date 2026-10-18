import {addDays, format, parseISO, startOfWeek} from 'date-fns';
import {formatInTimeZone} from 'date-fns-tz';

export type CalendarView = 'day' | 'week';

const DATE = /^\d{4}-\d{2}-\d{2}$/;

/** A "yyyy-MM-dd" that is a real calendar date. */
export function isDate(value: string | null): value is string {
  return (
    value !== null &&
    DATE.test(value) &&
    format(parseISO(value), 'yyyy-MM-dd') === value
  );
}

/** Today's date at the dealership, whatever the browser's timezone is. */
export function todayIn(timeZone: string, now: Date = new Date()): string {
  return formatInTimeZone(now, timeZone, 'yyyy-MM-dd');
}

/** The date an appointment falls on at the dealership. */
export function dayOf(iso: string, timeZone: string): string {
  return formatInTimeZone(new Date(iso), timeZone, 'yyyy-MM-dd');
}

export interface Range {
  /** Every date shown, in order (a Monday-to-Sunday week, or the one day). */
  days: string[];
  /** The API period: from is the first day's midnight, to the midnight after the last (dates at the dealership). */
  from: string;
  to: string;
}

export function rangeOf(date: string, view: CalendarView): Range {
  const first =
    view === 'day'
      ? parseISO(date)
      : startOfWeek(parseISO(date), {weekStartsOn: 1});
  const count = view === 'day' ? 1 : 7;
  const days = Array.from({length: count}, (_, i) =>
    format(addDays(first, i), 'yyyy-MM-dd'),
  );
  return {
    days,
    from: days[0] as string,
    to: format(addDays(first, count), 'yyyy-MM-dd'),
  };
}

/** The date a step earlier or later: a day, or a week. */
export function shift(
  date: string,
  view: CalendarView,
  direction: -1 | 1,
): string {
  return format(
    addDays(parseISO(date), direction * (view === 'day' ? 1 : 7)),
    'yyyy-MM-dd',
  );
}

/** "Tue, Oct 20" */
export function dayLabel(date: string): string {
  return format(parseISO(date), 'EEE, MMM d');
}

/** "Oct 19" and "Oct 25, 2026": the ends of a range, for the title. */
export function rangeLabels(range: Range): {from: string; to: string} {
  const last = parseISO(range.days[range.days.length - 1] as string);
  const first = parseISO(range.days[0] as string);
  return {
    from: format(first, 'MMM d'),
    to: format(last, 'MMM d, yyyy'),
  };
}
