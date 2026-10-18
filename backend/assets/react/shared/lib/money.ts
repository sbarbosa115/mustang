/** Integer cents → "$24,999" (no cents shown when they are zero). */
export function formatCents(
  cents: number | null | undefined,
  locale = 'en-US',
): string {
  if (cents == null) return '';
  const dollars = cents / 100;
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(dollars);
}

/** "12,345 mi" with grouping, for odometers. */
export function formatMileage(
  miles: number | null | undefined,
  locale = 'en-US',
): string {
  if (miles == null) return '';
  return `${new Intl.NumberFormat(locale).format(miles)} mi`;
}
