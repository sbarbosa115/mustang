import {useQuery} from '@tanstack/react-query';
import {conversationsKeys, fetchConversation} from '@/entities/conversation';
import {customersKeys, fetchCustomer} from '@/entities/customer';

/** The conversation and its messages; polled so a customer's reply shows up without a reload. */
export function useConversationDetail(conversationId: string) {
  return useQuery({
    queryKey: conversationsKeys.detail(conversationId),
    queryFn: () => fetchConversation(conversationId),
    refetchInterval: 15_000,
  });
}

export function useCustomer(customerId: string | undefined) {
  return useQuery({
    queryKey: customersKeys.detail(customerId ?? ''),
    queryFn: () => fetchCustomer(customerId ?? ''),
    enabled: customerId !== undefined,
  });
}
