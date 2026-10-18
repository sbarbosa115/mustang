import {api, unwrap, type Schema} from '@/shared/api';

export type MfaStatus = Schema<'MfaStatusOutput'>;

/** The second step of sign-in: a code from the app or a recovery code. */
export async function verifyMfa(
  body: Partial<Schema<'MfaVerifyInput'>>,
): Promise<MfaStatus> {
  return unwrap(
    await api.POST('/api/v1/auth/mfa/verify', {
      body: body as Schema<'MfaVerifyInput'>,
    }),
  ) as MfaStatus;
}
