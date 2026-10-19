// Typed calls to the message endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';

export const messagesKeys = {
  all: ['messages'] as const,
  list: (params: object = {}) => ['messages', 'list', params] as const,
  detail: (id: string) => ['messages', 'detail', id] as const,
};

export async function sendMessage(
  id: string,
  body: Schema<'SendMessageInput'>,
): Promise<Schema<'MessageOutput'>> {
  return unwrap(
    await api.POST('/api/v1/conversations/{id}/messages', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'MessageOutput'>;
}
export async function fetchProvenance(
  id: string,
): Promise<Schema<'ProvenanceOutput'>> {
  return unwrap(
    await api.GET('/api/v1/messages/{id}/provenance', {params: {path: {id}}}),
  ) as Schema<'ProvenanceOutput'>;
}
