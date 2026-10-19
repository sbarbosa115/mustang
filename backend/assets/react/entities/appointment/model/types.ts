import type {Schema} from '@/shared/api';

export type AppointmentList = Schema<'AppointmentListOutput'>;
export type Appointment = Schema<'AppointmentOutput'>;
export type BookAppointmentInput = Schema<'BookAppointmentInput'>;
export type UpdateAppointmentInput = Schema<'UpdateAppointmentInput'>;
