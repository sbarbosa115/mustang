import {describe, expect, it} from 'vitest';
import {
  EMPTY_FILTERS,
  activeFilterCount,
  filtersFromParams,
  filtersToParams,
  isFiltered,
  toApiQuery,
} from './filters';

describe('inventory filters', () => {
  it('round-trips through the URL and leaves defaults out of it', () => {
    const filters = {
      ...EMPTY_FILTERS,
      make: 'Ford',
      priceMax: '40000',
      sort: 'price_asc',
    };
    const params = filtersToParams(filters, 3);

    expect(params.toString()).toBe(
      'make=Ford&price_max=40000&sort=price_asc&page=3',
    );
    expect(filtersFromParams(params)).toEqual(filters);
    expect(filtersToParams(EMPTY_FILTERS).toString()).toBe('');
  });

  it('ignores an unknown sort from the URL', () => {
    expect(filtersFromParams(new URLSearchParams('sort=banana')).sort).toBe(
      'recent',
    );
  });

  it('sends whole dollars and skips blanks and junk', () => {
    const query = toApiQuery(
      {...EMPTY_FILTERS, q: ' f-150 ', priceMin: '25000', yearMin: 'abc'},
      2,
      25,
    );

    expect(query).toMatchObject({
      page: 2,
      per_page: 25,
      q: 'f-150',
      price_min: 25000,
    });
    expect(query.year_min).toBeUndefined();
    expect(query.sort).toBeUndefined();
  });

  it('counts narrowing filters but not the search or the sort', () => {
    const filters = {
      ...EMPTY_FILTERS,
      q: 'ford',
      sort: 'price_desc',
      make: 'Ford',
      condition: 'new',
    };

    expect(activeFilterCount(filters)).toBe(2);
    expect(isFiltered({...EMPTY_FILTERS, sort: 'price_desc'})).toBe(false);
    expect(isFiltered({...EMPTY_FILTERS, q: 'x'})).toBe(true);
  });
});
