// Typed calls to the worklist-item endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const worklistKeys = {
  all: ['worklist'] as const,
  list: (params: object = {}) => ['worklist', 'list', params] as const,
  detail: (id: string) => ['worklist', 'detail', id] as const,
};

export async function fetchContactNow(
  query: Query<'/api/v1/worklist/contact-now'> = {},
): Promise<Schema<'WorklistOutput'>> {
  return unwrap(
    await api.GET('/api/v1/worklist/contact-now', {params: {query}}),
  ) as Schema<'WorklistOutput'>;
}
