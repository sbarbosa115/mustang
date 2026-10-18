import {describe, expect, it} from 'vitest';
import {
  EMPTY_AUDIT_FILTERS,
  dayAfter,
  filtersFromParams,
  filtersToParams,
  isAuditFiltered,
  toAuditQuery,
} from './filters';

describe('audit filters', () => {
  it('reads the filters from the URL and drops a date that is not one', () => {
    const values = filtersFromParams(
      new URLSearchParams(
        'entity_type=Deal&user_id=u1&from=2026-10-01&to=soon',
      ),
    );
    expect(values).toEqual({
      ...EMPTY_AUDIT_FILTERS,
      entityType: 'Deal',
      userId: 'u1',
      from: '2026-10-01',
    });
    expect(isAuditFiltered(values)).toBe(true);
    expect(isAuditFiltered(EMPTY_AUDIT_FILTERS)).toBe(false);
  });

  it('writes the filters back, keeping other parameters and going back to page 1', () => {
    const next = filtersToParams(
      {...EMPTY_AUDIT_FILTERS, entityType: 'User'},
      new URLSearchParams('page=3&user_id=u1&tab=x'),
    );
    expect(next.toString()).toBe('tab=x&entity_type=User');
  });

  it('includes the last day of the period, across a month and a year end', () => {
    expect(dayAfter('2026-10-31')).toBe('2026-11-01');
    expect(dayAfter('2026-12-31')).toBe('2027-01-01');
    expect(
      toAuditQuery(
        {...EMPTY_AUDIT_FILTERS, from: '2026-10-01', to: '2026-10-01'},
        2,
        25,
      ),
    ).toEqual({page: 2, per_page: 25, from: '2026-10-01', to: '2026-10-02'});
  });
});
