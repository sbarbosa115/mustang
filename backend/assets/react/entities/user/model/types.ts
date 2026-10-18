import type {Schema} from '@/shared/api';

export type Me = Schema<'MeOutput'>;
export type User = Schema<'UserOutput'>;
export type UserPage = Schema<'UserPageOutput'>;
export type CreateUserInput = Schema<'CreateUserInput'>;
export type PreferencesInput = Schema<'PreferencesInput'>;
export type UpdateUserInput = Schema<'UpdateUserInput'>;
