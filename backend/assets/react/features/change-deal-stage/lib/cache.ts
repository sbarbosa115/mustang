import type {QueryClient} from '@tanstack/react-query';
import {dealsKeys, type Deal} from '@/entities/deal';

/** After a change: the deal's own query gets the answer, every deal list and the customer's record refetch. */
export function dealChanged(queryClient: QueryClient, deal: Deal): void {
  queryClient.setQueryData(dealsKeys.detail(deal.id), deal);
  void queryClient.invalidateQueries({queryKey: ['deals', 'list']});
  void queryClient.invalidateQueries({
    queryKey: ['customers', 'detail', deal.customer.id],
  });
}
