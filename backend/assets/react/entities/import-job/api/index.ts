// Typed calls to the import-job endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const importsKeys = {
  all: ['imports'] as const,
  list: (params: object = {}) => ['imports', 'list', params] as const,
  detail: (id: string) => ['imports', 'detail', id] as const,
};

export async function listImports(
  query: Query<'/api/v1/imports'> = {},
): Promise<Schema<'ImportJobPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/imports', {params: {query}}),
  ) as Schema<'ImportJobPageOutput'>;
}
export async function startImport(
  type: string,
  file: File,
): Promise<Schema<'ImportJobOutput'>> {
  const form = new FormData();
  form.append('type', type);
  form.append('file', file);
  return unwrap(
    await api.POST('/api/v1/imports', {
      body: form as never,
      bodySerializer: (b) => b as unknown as FormData,
    }),
  ) as Schema<'ImportJobOutput'>;
}
export async function fetchImport(
  id: string,
): Promise<Schema<'ImportJobOutput'>> {
  return unwrap(
    await api.GET('/api/v1/imports/{id}', {params: {path: {id}}}),
  ) as Schema<'ImportJobOutput'>;
}
