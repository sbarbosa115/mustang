// Typed calls to the vehicle endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const vehiclesKeys = {
  all: ['vehicles'] as const,
  list: (params: object = {}) => ['vehicles', 'list', params] as const,
  detail: (id: string) => ['vehicles', 'detail', id] as const,
};

export async function listVehicles(
  query: Query<'/api/v1/vehicles'> = {},
): Promise<Schema<'VehiclePageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/vehicles', {params: {query}}),
  ) as Schema<'VehiclePageOutput'>;
}
export async function createVehicle(
  body: Schema<'VehicleInput'>,
): Promise<Schema<'VehicleOutput'>> {
  return unwrap(
    await api.POST('/api/v1/vehicles', {body}),
  ) as Schema<'VehicleOutput'>;
}
export async function fetchVehicleFacets(): Promise<
  Schema<'VehicleFacetsOutput'>
> {
  return unwrap(
    await api.GET('/api/v1/vehicles/facets'),
  ) as Schema<'VehicleFacetsOutput'>;
}
export async function fetchVehicle(
  id: string,
): Promise<Schema<'VehicleOutput'>> {
  return unwrap(
    await api.GET('/api/v1/vehicles/{id}', {params: {path: {id}}}),
  ) as Schema<'VehicleOutput'>;
}
export async function updateVehicle(
  id: string,
  body: Schema<'UpdateVehicleInput'>,
): Promise<Schema<'VehicleOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/vehicles/{id}', {params: {path: {id}}, body}),
  ) as Schema<'VehicleOutput'>;
}
export async function changeVehicleStatus(
  id: string,
  body: Schema<'VehicleStatusInput'>,
): Promise<Schema<'VehicleOutput'>> {
  return unwrap(
    await api.POST('/api/v1/vehicles/{id}/status', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'VehicleOutput'>;
}
export async function uploadVehiclesCsv(
  file: File,
): Promise<Schema<'VehicleImportOutput'>> {
  const form = new FormData();
  form.append('file', file);
  return unwrap(
    await api.POST('/api/v1/vehicles/import', {
      body: form as never,
      bodySerializer: (b) => b as unknown as FormData,
    }),
  ) as Schema<'VehicleImportOutput'>;
}
export async function fetchVehicleImport(
  id: string,
): Promise<Schema<'VehicleImportOutput'>> {
  return unwrap(
    await api.GET('/api/v1/vehicles/import/{id}', {params: {path: {id}}}),
  ) as Schema<'VehicleImportOutput'>;
}
