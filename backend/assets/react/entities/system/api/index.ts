// Typed call to the health endpoint.
import {api, unwrap, type Schema} from '@/shared/api';

export const systemKeys = {
  all: ['system'] as const,
  health: () => ['system', 'health'] as const,
};

export async function fetchHealth(): Promise<Schema<'HealthOutput'>> {
  return unwrap(await api.GET('/api/v1/health')) as Schema<'HealthOutput'>;
}
