// Typed calls to the deal endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const dealsKeys = {
  all: ['deals'] as const,
  list: (params: object = {}) => ['deals', 'list', params] as const,
  detail: (id: string) => ['deals', 'detail', id] as const,
};

export async function listDeals(
  query: Query<'/api/v1/deals'> = {},
): Promise<Schema<'DealPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/deals', {params: {query}}),
  ) as Schema<'DealPageOutput'>;
}
export async function createDeal(
  body: Schema<'CreateDealInput'>,
): Promise<Schema<'DealOutput'>> {
  return unwrap(
    await api.POST('/api/v1/deals', {body}),
  ) as Schema<'DealOutput'>;
}
export async function fetchDeal(id: string): Promise<Schema<'DealOutput'>> {
  return unwrap(
    await api.GET('/api/v1/deals/{id}', {params: {path: {id}}}),
  ) as Schema<'DealOutput'>;
}
export async function changeStage(
  id: string,
  body: Schema<'ChangeStageInput'>,
): Promise<Schema<'DealOutput'>> {
  return unwrap(
    await api.POST('/api/v1/deals/{id}/stage', {params: {path: {id}}, body}),
  ) as Schema<'DealOutput'>;
}
export async function attachVehicle(
  id: string,
  body: Schema<'AttachVehicleInput'>,
): Promise<Schema<'DealOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/deals/{id}/vehicle', {params: {path: {id}}, body}),
  ) as Schema<'DealOutput'>;
}
export async function setTradeIn(
  id: string,
  body: Schema<'TradeInInput'>,
): Promise<Schema<'DealOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/deals/{id}/trade-in', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'DealOutput'>;
}
export async function assignDeal(
  id: string,
  body: Schema<'DealAssigneeInput'>,
): Promise<Schema<'DealOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/deals/{id}/assignee', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'DealOutput'>;
}
