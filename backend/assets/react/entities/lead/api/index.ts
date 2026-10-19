// Typed calls to the lead endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const leadsKeys = {
  all: ['leads'] as const,
  list: (params: object = {}) => ['leads', 'list', params] as const,
  detail: (id: string) => ['leads', 'detail', id] as const,
};

export async function listLeads(
  query: Query<'/api/v1/leads'> = {},
): Promise<Schema<'LeadPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/leads', {params: {query}}),
  ) as Schema<'LeadPageOutput'>;
}
export async function captureLead(
  body: Schema<'CaptureLeadInput'>,
): Promise<Schema<'LeadOutput'>> {
  return unwrap(
    await api.POST('/api/v1/leads', {body}),
  ) as Schema<'LeadOutput'>;
}
export async function fetchLead(id: string): Promise<Schema<'LeadOutput'>> {
  return unwrap(
    await api.GET('/api/v1/leads/{id}', {params: {path: {id}}}),
  ) as Schema<'LeadOutput'>;
}
export async function assignLead(
  id: string,
  body: Schema<'LeadAssigneeInput'>,
): Promise<Schema<'LeadOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/leads/{id}/assignee', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'LeadOutput'>;
}
export async function closeLead(
  id: string,
  body: Schema<'CloseLeadInput'>,
): Promise<Schema<'LeadOutput'>> {
  return unwrap(
    await api.POST('/api/v1/leads/{id}/close', {params: {path: {id}}, body}),
  ) as Schema<'LeadOutput'>;
}
export async function fetchRouting(): Promise<Schema<'RoutingOutput'>> {
  return unwrap(
    await api.GET('/api/v1/settings/routing'),
  ) as Schema<'RoutingOutput'>;
}
export async function saveRouting(
  body: Schema<'RoutingInput'>,
): Promise<Schema<'RoutingOutput'>> {
  return unwrap(
    await api.PUT('/api/v1/settings/routing', {body}),
  ) as Schema<'RoutingOutput'>;
}
