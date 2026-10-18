import {formatInTimeZone, fromZonedTime} from 'date-fns-tz';

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/**
 * "5 min ago" under 24 hours, a date after (DESIGN.md "Times"). The caller shows the full timestamp in a tooltip
 * with formatDateTime.
 */
export function formatRelative(
  iso: string,
  now: Date = new Date(),
  timeZone = 'America/New_York',
  locale = 'en',
): string {
  const at = new Date(iso);
  const diff = now.getTime() - at.getTime();
  const rtf = new Intl.RelativeTimeFormat(locale, {
    numeric: 'auto',
    style: 'short',
  });
  if (Math.abs(diff) < MINUTE) return rtf.format(0, 'second');
  if (Math.abs(diff) < HOUR)
    return rtf.format(-Math.round(diff / MINUTE), 'minute');
  if (Math.abs(diff) < DAY) return rtf.format(-Math.round(diff / HOUR), 'hour');
  return formatInTimeZone(at, timeZone, 'MMM d, yyyy');
}

/** The full date and time in the dealership's timezone, e.g. "Oct 18, 2026, 3:30 PM EDT". */
export function formatDateTime(
  iso: string,
  timeZone = 'America/New_York',
): string {
  return formatInTimeZone(new Date(iso), timeZone, 'MMM d, yyyy, h:mm a zzz');
}

/** Only the time in the dealership's timezone, e.g. "3:30 PM". */
export function formatTime(iso: string, timeZone = 'America/New_York'): string {
  return formatInTimeZone(new Date(iso), timeZone, 'h:mm a');
}

/** A local "yyyy-MM-ddTHH:mm" typed in the dealership's timezone, as a UTC instant. */
export function localToUtc(local: string, timeZone = 'America/New_York'): Date {
  return fromZonedTime(local, timeZone);
}

/** Whole days between two instants, never negative; used for "days in stage". */
export function daysBetween(fromIso: string, now: Date = new Date()): number {
  return Math.max(
    0,
    Math.floor((now.getTime() - new Date(fromIso).getTime()) / DAY),
  );
}
