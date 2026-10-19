// Typed calls to the intervention endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';

export const interventionsKeys = {
  all: ['interventions'] as const,
  list: (params: object = {}) => ['interventions', 'list', params] as const,
  detail: (id: string) => ['interventions', 'detail', id] as const,
};

export async function fetchInterventions(): Promise<
  Schema<'InterventionListOutput'>
> {
  return unwrap(
    await api.GET('/api/v1/worklist/interventions'),
  ) as Schema<'InterventionListOutput'>;
}
