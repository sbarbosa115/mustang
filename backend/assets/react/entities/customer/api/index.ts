// Typed calls to the customer endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const customersKeys = {
  all: ['customers'] as const,
  list: (params: object = {}) => ['customers', 'list', params] as const,
  detail: (id: string) => ['customers', 'detail', id] as const,
};

export async function listCustomers(
  query: Query<'/api/v1/customers'> = {},
): Promise<Schema<'CustomerPageOutput'>> {
  return unwrap(
    await api.GET('/api/v1/customers', {params: {query}}),
  ) as Schema<'CustomerPageOutput'>;
}
export async function lookupCustomers(
  query: Query<'/api/v1/customers/lookup'> = {},
): Promise<Schema<'CustomerLookupOutput'>> {
  return unwrap(
    await api.GET('/api/v1/customers/lookup', {params: {query}}),
  ) as Schema<'CustomerLookupOutput'>;
}
export async function fetchCustomer(
  id: string,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.GET('/api/v1/customers/{id}', {params: {path: {id}}}),
  ) as Schema<'CustomerOutput'>;
}
export async function createCustomer(
  body: Schema<'CreateCustomerInput'>,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.POST('/api/v1/customers', {body}),
  ) as Schema<'CustomerOutput'>;
}
export async function updateCustomer(
  id: string,
  body: Schema<'UpdateCustomerInput'>,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/customers/{id}', {params: {path: {id}}, body}),
  ) as Schema<'CustomerOutput'>;
}
export async function addContact(
  id: string,
  body: Schema<'ContactInput'>,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.POST('/api/v1/customers/{id}/contacts', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'CustomerOutput'>;
}
export async function updateContact(
  id: string,
  contactId: string,
  body: Schema<'UpdateContactInput'>,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/customers/{id}/contacts/{contactId}', {
      params: {path: {id, contactId}},
      body,
    }),
  ) as Schema<'CustomerOutput'>;
}
export async function removeContact(
  id: string,
  contactId: string,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.DELETE('/api/v1/customers/{id}/contacts/{contactId}', {
      params: {path: {id, contactId}},
    }),
  ) as Schema<'CustomerOutput'>;
}
export async function addOwnedVehicle(
  id: string,
  body: Schema<'OwnedVehicleInput'>,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.POST('/api/v1/customers/{id}/vehicles-owned', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'CustomerOutput'>;
}
export async function removeOwnedVehicle(
  id: string,
  vehicleId: string,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.DELETE('/api/v1/customers/{id}/vehicles-owned/{vehicleId}', {
      params: {path: {id, vehicleId}},
    }),
  ) as Schema<'CustomerOutput'>;
}
export async function assignTeam(
  id: string,
  body: Schema<'AssignmentsInput'>,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/customers/{id}/assignments', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'CustomerOutput'>;
}
export async function listNotes(id: string): Promise<Schema<'NoteOutput'>[]> {
  return unwrap(
    await api.GET('/api/v1/customers/{id}/notes', {params: {path: {id}}}),
  ) as Schema<'NoteOutput'>[];
}
export async function addNote(
  id: string,
  body: Schema<'NoteInput'>,
): Promise<Schema<'NoteOutput'>> {
  return unwrap(
    await api.POST('/api/v1/customers/{id}/notes', {
      params: {path: {id}},
      body,
    }),
  ) as Schema<'NoteOutput'>;
}
export async function archiveCustomer(
  id: string,
): Promise<Schema<'CustomerOutput'>> {
  return unwrap(
    await api.POST('/api/v1/customers/{id}/archive', {params: {path: {id}}}),
  ) as Schema<'CustomerOutput'>;
}
