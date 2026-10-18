import {formatInTimeZone} from 'date-fns-tz';
import {useEffect} from 'react';

const PREFIX = 'mustang.today.seen';

function read(key: string): string[] {
  try {
    const raw = window.localStorage.getItem(key);
    const parsed: unknown = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed)
      ? parsed.filter((id): id is string => typeof id === 'string')
      : [];
  } catch {
    return [];
  }
}

function write(key: string, ids: string[]): void {
  try {
    window.localStorage.setItem(key, JSON.stringify(ids));
  } catch {
    // Storage blocked or full: progress is a convenience, the list works without it.
  }
}

/**
 * How many of today's customers are done: every customer seen on the list today (in the dealership's day, per user,
 * in this browser) who is no longer on it. The list is computed on request, so a customer leaves it once handled.
 */
export function useDailyProgress(
  userId: string | undefined,
  timeZone: string | undefined,
  currentIds: string[] | undefined,
): {done: number; total: number} | null {
  const key =
    userId && currentIds
      ? `${PREFIX}.${userId}.${formatInTimeZone(new Date(), timeZone ?? 'America/New_York', 'yyyy-MM-dd')}`
      : null;
  const seen = key ? read(key) : [];
  const merged = currentIds
    ? [...seen, ...currentIds.filter((id) => !seen.includes(id))]
    : seen;
  const signature = merged.join(',');

  useEffect(() => {
    if (key) write(key, signature ? signature.split(',') : []);
  }, [key, signature]);

  if (!key || !currentIds) return null;
  const current = new Set(currentIds);
  return {
    done: merged.filter((id) => !current.has(id)).length,
    total: merged.length,
  };
}
