import {describe, expect, it} from 'vitest';
import {daysBetween, formatRelative} from './dates';
import {formatCents, formatMileage} from './money';
import {formatPhone} from './phone';

describe('formatting', () => {
  it('writes recent times relatively and older ones as a date in the dealership timezone', () => {
    const now = new Date('2026-10-18T20:00:00Z');
    expect(formatRelative('2026-10-18T19:55:00Z', now)).toBe('5 min. ago');
    expect(formatRelative('2026-10-15T15:00:00Z', now)).toBe('Oct 15, 2026');
  });

  it('counts whole days in a stage', () => {
    expect(
      daysBetween('2026-10-12T21:00:00Z', new Date('2026-10-18T20:00:00Z')),
    ).toBe(5);
  });

  it('shows money without cents when there are none', () => {
    expect(formatCents(2899900)).toBe('$28,999');
    expect(formatCents(1050)).toBe('$10.50');
    expect(formatMileage(12345)).toBe('12,345 mi');
  });

  it('shows US numbers the national way', () => {
    expect(formatPhone('+18135551001')).toBe('(813) 555-1001');
  });
});
