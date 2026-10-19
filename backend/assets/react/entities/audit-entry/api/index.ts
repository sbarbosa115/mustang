// Typed calls to the audit-entry endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const auditKeys = {
  all: ['audit'] as const,
  list: (params: object = {}) => ['audit', 'list', params] as const,
  detail: (id: string) => ['audit', 'detail', id] as const,
};

export async function listAuditEntries(
  query: Query<'/api/v1/audit'> = {},
): Promise<Schema<'AuditPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/audit', {params: {query}}),
  ) as Schema<'AuditPageOutput'>;
}
