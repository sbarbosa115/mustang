import {addDays, format, parseISO} from 'date-fns';
import {formatInTimeZone} from 'date-fns-tz';

export interface Range {
  from: string;
  to: string;
}

export const PRESET_DAYS = [7, 30, 90] as const;
export const DEFAULT_DAYS = 30;

/** Today's date (yyyy-MM-dd) on the dealership's clock. */
export function todayIn(timeZone: string, now: Date = new Date()): string {
  return formatInTimeZone(now, timeZone, 'yyyy-MM-dd');
}

/** The last `days` days ending today, both ends inclusive. */
export function lastDays(days: number, today: string): Range {
  return {
    from: format(addDays(parseISO(today), -(days - 1)), 'yyyy-MM-dd'),
    to: today,
  };
}

/** A range is usable when both ends are dates and the start is not after the end. */
export function isValidRange(from: string, to: string): boolean {
  return (
    /^\d{4}-\d{2}-\d{2}$/.test(from) &&
    /^\d{4}-\d{2}-\d{2}$/.test(to) &&
    from <= to
  );
}

/** Which preset a range is, if any. */
export function presetOf(range: Range, today: string): number | null {
  return (
    PRESET_DAYS.find((d) => {
      const r = lastDays(d, today);
      return r.from === range.from && r.to === range.to;
    }) ?? null
  );
}
