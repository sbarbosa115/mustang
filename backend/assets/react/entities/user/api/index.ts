// Typed calls to the user endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const usersKeys = {
  all: ['users'] as const,
  list: (params: object = {}) => ['users', 'list', params] as const,
  detail: (id: string) => ['users', 'detail', id] as const,
};

export async function fetchMe(): Promise<Schema<'MeOutput'>> {
  return unwrap(await api.GET('/api/v1/me')) as Schema<'MeOutput'>;
}
export async function updatePreferences(
  body: Schema<'PreferencesInput'>,
): Promise<Schema<'MeOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/me/preferences', {body}),
  ) as Schema<'MeOutput'>;
}
export async function listUsers(
  query: Query<'/api/v1/users'> = {},
): Promise<Schema<'UserPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/users', {params: {query}}),
  ) as Schema<'UserPageOutput'>;
}
export async function fetchUser(id: string): Promise<Schema<'UserOutput'>> {
  return unwrap(
    await api.GET('/api/v1/users/{id}', {params: {path: {id}}}),
  ) as Schema<'UserOutput'>;
}
export async function createUser(
  body: Schema<'CreateUserInput'>,
): Promise<Schema<'UserOutput'>> {
  return unwrap(
    await api.POST('/api/v1/users', {body}),
  ) as Schema<'UserOutput'>;
}
export async function updateUser(
  id: string,
  body: Schema<'UpdateUserInput'>,
): Promise<Schema<'UserOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/users/{id}', {params: {path: {id}}, body}),
  ) as Schema<'UserOutput'>;
}
export async function deactivateUser(
  id: string,
): Promise<Schema<'UserOutput'>> {
  return unwrap(
    await api.POST('/api/v1/users/{id}/deactivate', {params: {path: {id}}}),
  ) as Schema<'UserOutput'>;
}
export async function activateUser(id: string): Promise<Schema<'UserOutput'>> {
  return unwrap(
    await api.POST('/api/v1/users/{id}/activate', {params: {path: {id}}}),
  ) as Schema<'UserOutput'>;
}
export async function resetUserMfa(id: string): Promise<void> {
  unwrap(await api.DELETE('/api/v1/users/{id}/mfa', {params: {path: {id}}}));
}
