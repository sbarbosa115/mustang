// Typed calls to the kpi endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const kpisKeys = {
  all: ['kpis'] as const,
  list: (params: object = {}) => ['kpis', 'list', params] as const,
  detail: (id: string) => ['kpis', 'detail', id] as const,
};

export async function fetchKpis(
  query: Query<'/api/v1/reports/kpis'> = {},
): Promise<Schema<'KpiReportOutput'>> {
  return unwrap(
    await api.GET('/api/v1/reports/kpis', {params: {query}}),
  ) as Schema<'KpiReportOutput'>;
}
