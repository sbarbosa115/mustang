import {useQuery} from '@tanstack/react-query';
import {fetchHealth, systemKeys} from '@/entities/system';

/** The environments where the API exposes POST /api/v1/dev/inbound (its route condition). */
const DEV_ENVIRONMENTS = ['dev', 'test'];

/**
 * Whether the API exposes the dev inbound endpoint, read from the health check's environment. A probe of the
 * endpoint itself would log a refused request in the browser console on every visit.
 */
export async function fetchDevInboundAvailable(): Promise<boolean> {
  try {
    const health = await fetchHealth();
    return DEV_ENVIRONMENTS.includes(health.environment);
  } catch {
    return false;
  }
}

export function useDevInboundAvailable(): boolean {
  const {data} = useQuery({
    // Not under the conversations key: invalidating conversations must not ask again.
    queryKey: [...systemKeys.health(), 'dev-inbound'],
    queryFn: fetchDevInboundAvailable,
    staleTime: Infinity,
    gcTime: Infinity,
    refetchOnWindowFocus: false,
  });
  return data === true;
}
