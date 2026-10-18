import {useQuery} from '@tanstack/react-query';
import {simulateInbound} from '@/entities/conversation';
import {ApiError} from '@/shared/api';

/**
 * Whether the API exposes POST /dev/inbound (dev and test only). An empty body is refused by validation (422) when
 * the route exists and is 404 when it does not, so the probe never adds a message.
 */
const EMPTY = {from: null, channel: null, body: null, subject: null};

async function probe(): Promise<boolean> {
  try {
    await simulateInbound(EMPTY);
    return true;
  } catch (error) {
    return error instanceof ApiError && error.status === 422;
  }
}

export function useDevInboundAvailable(): boolean {
  const {data} = useQuery({
    // Not under the conversations key: invalidating conversations must not probe again.
    queryKey: ['dev-inbound', 'available'],
    queryFn: probe,
    staleTime: Infinity,
    gcTime: Infinity,
    refetchOnWindowFocus: false,
  });
  return data === true;
}
