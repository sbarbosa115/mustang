import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {assignDeal, dealsKeys} from '@/entities/deal';
import {interventionsKeys} from '@/entities/intervention';
import {useAuth} from '@/entities/user';
import {worklistKeys} from '@/entities/worklist-item';

/**
 * "Assign to me": the signed-in manager takes the deal (PATCH /deals/{id}/assignee). The queue, the contact-now
 * lists and the deal lists refetch, since the deal's owner decides whose list its customer is on.
 */
export function useAssignToMe() {
  const {t} = useTranslation('team');
  const {me} = useAuth();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (dealId: string) =>
      assignDeal(dealId, {user_id: me?.user.id ?? null}),
    onSuccess: (deal) => {
      queryClient.setQueryData(dealsKeys.detail(deal.id), deal);
      void queryClient.invalidateQueries({queryKey: interventionsKeys.all});
      void queryClient.invalidateQueries({queryKey: worklistKeys.all});
      void queryClient.invalidateQueries({queryKey: ['deals', 'list']});
      toast.success(t('assign.done', {name: deal.customer.name}));
    },
    onError: () => toast.error(t('assign.error')),
  });
}
