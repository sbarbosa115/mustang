// Typed calls to the consent endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const consentsKeys = {
  all: ['consents'] as const,
  list: (params: object = {}) => ['consents', 'list', params] as const,
  detail: (id: string) => ['consents', 'detail', id] as const,
};

export async function listConsents(
  id: string,
): Promise<Schema<'ConsentListOutput'>> {
  return unwrap(
    await api.GET('/api/v1/customers/{id}/consents', {params: {path: {id}}}),
  ) as Schema<'ConsentListOutput'>;
}
export async function recordConsent(
  id: string,
  body: Schema<'RecordConsentInput'>,
): Promise<Schema<'ConsentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/customers/{id}/consents', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'ConsentOutput'>;
}
export async function revokeConsent(
  id: string,
  body: Schema<'RevokeConsentInput'>,
): Promise<Schema<'ConsentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/consents/{id}/revoke', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'ConsentOutput'>;
}
export async function listSuppressions(
  query: Query<'/api/v1/compliance/suppressions'> = {},
): Promise<Schema<'SuppressionPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/compliance/suppressions', {params: {query}}),
  ) as Schema<'SuppressionPageOutput'>;
}
