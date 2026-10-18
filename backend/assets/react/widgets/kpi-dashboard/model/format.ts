type Unit = 'minutes' | 'percent' | 'units';

const number = new Intl.NumberFormat('en', {maximumFractionDigits: 1});
const signed = new Intl.NumberFormat('en', {
  maximumFractionDigits: 1,
  signDisplay: 'exceptZero',
});

export function formatNumber(value: number): string {
  return number.format(value);
}

export function formatChange(value: number): string {
  return signed.format(value);
}

/** The change versus the previous period, or null when either side has no data. */
export function changeOf(
  value: number | null | undefined,
  previous: number | null | undefined,
): number | null {
  if (value == null || previous == null) return null;
  return Math.round((value - previous) * 10) / 10;
}

export type {Unit};
