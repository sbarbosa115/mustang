import type {Query} from '@/shared/api/query';

/** Every filter as the text a person typed or picked; empty means "not filtering". */
export interface InventoryFilters {
  q: string;
  status: string;
  condition: string;
  make: string;
  model: string;
  body: string;
  drivetrain: string;
  fuel: string;
  color: string;
  yearMin: string;
  yearMax: string;
  /** Whole dollars, like the API's price_min and price_max. */
  priceMin: string;
  priceMax: string;
  mileageMax: string;
  sort: string;
}

export const DEFAULT_SORT = 'recent';
export const SORTS = [
  'recent',
  'price_asc',
  'price_desc',
  'year_desc',
  'mileage_asc',
] as const;

export const EMPTY_FILTERS: InventoryFilters = {
  q: '',
  status: '',
  condition: '',
  make: '',
  model: '',
  body: '',
  drivetrain: '',
  fuel: '',
  color: '',
  yearMin: '',
  yearMax: '',
  priceMin: '',
  priceMax: '',
  mileageMax: '',
  sort: DEFAULT_SORT,
};

/** The URL parameter of each filter, so a filtered list can be shared and survives a reload. */
const PARAMS: Record<keyof InventoryFilters, string> = {
  q: 'q',
  status: 'status',
  condition: 'condition',
  make: 'make',
  model: 'model',
  body: 'body',
  drivetrain: 'drivetrain',
  fuel: 'fuel',
  color: 'color',
  yearMin: 'year_min',
  yearMax: 'year_max',
  priceMin: 'price_min',
  priceMax: 'price_max',
  mileageMax: 'mileage_max',
  sort: 'sort',
};

const KEYS = Object.keys(PARAMS) as Array<keyof InventoryFilters>;

export function filtersFromParams(params: URLSearchParams): InventoryFilters {
  const filters = {...EMPTY_FILTERS};
  for (const key of KEYS) {
    filters[key] = params.get(PARAMS[key]) ?? EMPTY_FILTERS[key];
  }
  if (!(SORTS as readonly string[]).includes(filters.sort)) {
    filters.sort = DEFAULT_SORT;
  }
  return filters;
}

/** Only what differs from "show everything, newest first", plus the page when it is not the first. */
export function filtersToParams(
  filters: InventoryFilters,
  page = 1,
): URLSearchParams {
  const params = new URLSearchParams();
  for (const key of KEYS) {
    const value = filters[key].trim();
    if (value !== '' && value !== EMPTY_FILTERS[key]) {
      params.set(PARAMS[key], value);
    }
  }
  if (page > 1) params.set('page', String(page));
  return params;
}

function whole(value: string): number | undefined {
  const n = Number.parseInt(value, 10);
  return Number.isFinite(n) && n >= 0 ? n : undefined;
}

type ListQuery = Query<'/api/v1/vehicles'>;

export function toApiQuery(
  filters: InventoryFilters,
  page: number,
  perPage: number,
): ListQuery {
  const text = (value: string) => (value.trim() === '' ? undefined : value);
  return {
    page,
    per_page: perPage,
    q: text(filters.q.trim()),
    status: (text(filters.status) as ListQuery['status']) ?? undefined,
    condition: (text(filters.condition) as ListQuery['condition']) ?? undefined,
    make: text(filters.make),
    model: text(filters.model),
    body: text(filters.body),
    drivetrain: text(filters.drivetrain),
    fuel: text(filters.fuel),
    color: text(filters.color),
    year_min: whole(filters.yearMin),
    year_max: whole(filters.yearMax),
    price_min: whole(filters.priceMin),
    price_max: whole(filters.priceMax),
    mileage_max: whole(filters.mileageMax),
    sort:
      filters.sort === DEFAULT_SORT
        ? undefined
        : (filters.sort as ListQuery['sort']),
  };
}

/** How many filters are narrowing the list (the search box and the sort order do not count). */
export function activeFilterCount(filters: InventoryFilters): number {
  return KEYS.filter(
    (key) => key !== 'q' && key !== 'sort' && filters[key].trim() !== '',
  ).length;
}

/** True when anything but the default view is asked for. */
export function isFiltered(filters: InventoryFilters): boolean {
  return filters.q.trim() !== '' || activeFilterCount(filters) > 0;
}
