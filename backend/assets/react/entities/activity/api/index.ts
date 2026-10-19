// Typed calls to the activity endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const timelineKeys = {
  all: ['timeline'] as const,
  list: (params: object = {}) => ['timeline', 'list', params] as const,
  detail: (id: string) => ['timeline', 'detail', id] as const,
};

export async function fetchTimeline(
  id: string,
  query: Query<'/api/v1/customers/{id}/timeline'> = {},
): Promise<Schema<'ActivityPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/customers/{id}/timeline', {
      params: {path: {id}, query},
    }),
  ) as Schema<'ActivityPageOutput'>;
}
