import type {TFunction} from 'i18next';
import type {Query} from '@/shared/api/query';

/** The record types the recorder writes (the entity's class name), in the order the filter offers them. */
export const ENTITY_TYPES = [
  'Customer',
  'CustomerContact',
  'CustomerOwnedVehicle',
  'CustomerNote',
  'Lead',
  'Deal',
  'Appointment',
  'Conversation',
  'Message',
  'MessageTemplate',
  'Consent',
  'Suppression',
  'Vehicle',
  'VehicleImport',
  'ImportJob',
  'User',
  'MfaCredential',
  'RoutingRotation',
  'Dealership',
  'Activity',
] as const;

/** Every filter as text; empty means "not filtering". Dates are YYYY-MM-DD in the dealership's time, both inclusive. */
export interface AuditFilterValues {
  entityType: string;
  entityId: string;
  userId: string;
  from: string;
  to: string;
}

export const EMPTY_AUDIT_FILTERS: AuditFilterValues = {
  entityType: '',
  entityId: '',
  userId: '',
  from: '',
  to: '',
};

/** The URL parameter of each filter, so a filtered log can be shared and survives a reload. */
const PARAMS: Record<keyof AuditFilterValues, string> = {
  entityType: 'entity_type',
  entityId: 'entity_id',
  userId: 'user_id',
  from: 'from',
  to: 'to',
};

const DATE = /^\d{4}-\d{2}-\d{2}$/;

export function filtersFromParams(params: URLSearchParams): AuditFilterValues {
  const values = {...EMPTY_AUDIT_FILTERS};
  for (const key of Object.keys(PARAMS) as Array<keyof AuditFilterValues>) {
    values[key] = params.get(PARAMS[key])?.trim() ?? '';
  }
  if (!DATE.test(values.from)) values.from = '';
  if (!DATE.test(values.to)) values.to = '';
  return values;
}

/** The filters as URL parameters; the page goes back to 1 because the list changed. */
export function filtersToParams(
  values: AuditFilterValues,
  previous: URLSearchParams = new URLSearchParams(),
): URLSearchParams {
  const next = new URLSearchParams(previous);
  for (const key of Object.keys(PARAMS) as Array<keyof AuditFilterValues>) {
    if (values[key]) next.set(PARAMS[key], values[key]);
    else next.delete(PARAMS[key]);
  }
  next.delete('page');
  return next;
}

export function isAuditFiltered(values: AuditFilterValues): boolean {
  return Object.values(values).some((value) => value !== '');
}

/** The day after a YYYY-MM-DD date: the API's `to` is exclusive, the person's "To" includes its day. */
export function dayAfter(date: string): string {
  const [year, month, day] = date.split('-').map(Number) as [
    number,
    number,
    number,
  ];
  return new Date(Date.UTC(year, month - 1, day + 1))
    .toISOString()
    .slice(0, 10);
}

export function toAuditQuery(
  values: AuditFilterValues,
  page: number,
  perPage: number,
): Query<'/api/v1/audit'> {
  return {
    page,
    per_page: perPage,
    ...(values.entityType ? {entity_type: values.entityType} : {}),
    ...(values.entityId ? {entity_id: values.entityId} : {}),
    ...(values.userId ? {user_id: values.userId} : {}),
    ...(values.from ? {from: values.from} : {}),
    ...(values.to ? {to: dayAfter(values.to)} : {}),
  };
}

/** A record type in words; a type this screen does not know yet shows as the API names it. */
export function entityLabel(t: TFunction<'audit'>, type: string): string {
  return (ENTITY_TYPES as readonly string[]).includes(type)
    ? t(`entity.${type as (typeof ENTITY_TYPES)[number]}`)
    : type;
}
