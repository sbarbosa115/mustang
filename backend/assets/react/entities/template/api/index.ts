// Typed calls to the template endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const templatesKeys = {
  all: ['templates'] as const,
  list: (params: object = {}) => ['templates', 'list', params] as const,
  detail: (id: string) => ['templates', 'detail', id] as const,
};

export async function listTemplates(
  query: Query<'/api/v1/templates'> = {},
): Promise<Schema<'TemplateListOutput'>> {
  return unwrap(
    await api.GET('/api/v1/templates', {params: {query}}),
  ) as Schema<'TemplateListOutput'>;
}
export async function createTemplate(
  body: Schema<'TemplateInput'>,
): Promise<Schema<'TemplateOutput'>> {
  return unwrap(
    await api.POST('/api/v1/templates', {body}),
  ) as Schema<'TemplateOutput'>;
}
export async function fetchTemplate(
  id: string,
): Promise<Schema<'TemplateOutput'>> {
  return unwrap(
    await api.GET('/api/v1/templates/{id}', {params: {path: {id}}}),
  ) as Schema<'TemplateOutput'>;
}
export async function updateTemplate(
  id: string,
  body: Schema<'UpdateTemplateInput'>,
): Promise<Schema<'TemplateOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/templates/{id}', {params: {path: {id}}, body}),
  ) as Schema<'TemplateOutput'>;
}
export async function deleteTemplate(id: string): Promise<void> {
  unwrap(await api.DELETE('/api/v1/templates/{id}', {params: {path: {id}}}));
}
