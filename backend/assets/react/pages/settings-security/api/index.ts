import {api, unwrap, type Schema} from '@/shared/api';

export type MfaStatus = Schema<'MfaStatusOutput'>;
export const mfaStatusKey = ['mfa', 'status'] as const;

/** The signed-in user's two-step sign-in: on or off, and the recovery codes left. */
export async function fetchMfaStatus(): Promise<MfaStatus> {
  return unwrap(await api.GET('/api/v1/auth/mfa')) as MfaStatus;
}
