// Typed calls to the appointment endpoints. Owned by item 0 (the contract); items use them, they do not change them.
import {api, unwrap, type Schema} from '@/shared/api';
import type {Query} from '@/shared/api/query';

export const appointmentsKeys = {
  all: ['appointments'] as const,
  list: (params: object = {}) => ['appointments', 'list', params] as const,
  detail: (id: string) => ['appointments', 'detail', id] as const,
};

export async function listAppointments(
  query: Query<'/api/v1/appointments'> = {},
): Promise<Schema<'AppointmentListOutput'>> {
  return unwrap(
    await api.GET('/api/v1/appointments', {params: {query}}),
  ) as Schema<'AppointmentListOutput'>;
}
export async function bookAppointment(
  body: Schema<'BookAppointmentInput'>,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/appointments', {body}),
  ) as Schema<'AppointmentOutput'>;
}
export async function fetchAppointment(
  id: string,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.GET('/api/v1/appointments/{id}', {params: {path: {id}}}),
  ) as Schema<'AppointmentOutput'>;
}
export async function updateAppointment(
  id: string,
  body: Schema<'UpdateAppointmentInput'>,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.PATCH('/api/v1/appointments/{id}', {params: {path: {id}}, body}),
  ) as Schema<'AppointmentOutput'>;
}
export async function confirmAppointment(
  id: string,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/appointments/{id}/confirm', {params: {path: {id}}}),
  ) as Schema<'AppointmentOutput'>;
}
export async function markShown(
  id: string,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/appointments/{id}/show', {params: {path: {id}}}),
  ) as Schema<'AppointmentOutput'>;
}
export async function markNoShow(
  id: string,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/appointments/{id}/no-show', {params: {path: {id}}}),
  ) as Schema<'AppointmentOutput'>;
}
export async function cancelAppointment(
  id: string,
): Promise<Schema<'AppointmentOutput'>> {
  return unwrap(
    await api.POST('/api/v1/appointments/{id}/cancel', {params: {path: {id}}}),
  ) as Schema<'AppointmentOutput'>;
}
