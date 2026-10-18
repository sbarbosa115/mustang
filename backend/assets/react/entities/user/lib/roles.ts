import type {Schema} from '@/shared/api';

export type Role = 'sales' | 'bdc' | 'manager' | 'admin';

/** True when the signed-in user holds the role, after the hierarchy (a manager has sales and bdc). */
export function hasRole(
  me: Schema<'MeOutput'> | null | undefined,
  role: Role,
): boolean {
  return me?.roles.includes(role) ?? false;
}
