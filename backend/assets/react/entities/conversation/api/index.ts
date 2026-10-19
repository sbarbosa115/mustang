// Typed calls to the conversation endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const conversationsKeys = {
  all: ['conversations'] as const,
  list: (params: object = {}) => ['conversations', 'list', params] as const,
  detail: (id: string) => ['conversations', 'detail', id] as const,
};

export async function listConversations(
  query: Query<'/api/v1/conversations'> = {},
): Promise<Schema<'ConversationPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/conversations', {params: {query}}),
  ) as Schema<'ConversationPageOutput'>;
}
export async function fetchConversationCounts(): Promise<
  Schema<'ConversationCountsOutput'>
> {
  return unwrap(
    await api.GET('/api/v1/conversations/counts'),
  ) as Schema<'ConversationCountsOutput'>;
}
export async function fetchConversation(
  id: string,
): Promise<Schema<'ConversationDetailOutput'>> {
  return unwrap(
    await api.GET('/api/v1/conversations/{id}', {params: {path: {id}}}),
  ) as Schema<'ConversationDetailOutput'>;
}
export async function markConversationRead(
  id: string,
): Promise<Schema<'ConversationOutput'>> {
  return unwrap(
    await api.POST('/api/v1/conversations/{id}/read', {params: {path: {id}}}),
  ) as Schema<'ConversationOutput'>;
}
export async function fetchCustomerConversation(
  id: string,
): Promise<Schema<'ConversationDetailOutput'>> {
  return unwrap(
    await api.GET('/api/v1/customers/{id}/conversation', {
      params: {path: {id}},
    }),
  ) as Schema<'ConversationDetailOutput'>;
}
export async function simulateInbound(
  body: Schema<'DevInboundInput'>,
): Promise<Schema<'DevInboundOutput'>> {
  return unwrap(
    await api.POST('/api/v1/dev/inbound', {body}),
  ) as Schema<'DevInboundOutput'>;
}
