import {api, unwrap, type Schema} from '@/shared/api';

export type MfaEnrollment = Schema<'MfaEnrollmentOutput'>;

/** Starts enrollment: a new secret, its otpauth URL and QR code. Starting again replaces an unconfirmed secret. */
export async function enrollMfa(): Promise<MfaEnrollment> {
  return unwrap(await api.POST('/api/v1/auth/mfa/enroll')) as MfaEnrollment;
}

/** Proves the app has the secret; answers the recovery codes, shown this once. */
export async function confirmMfa(code: string): Promise<string[]> {
  const result = unwrap(
    await api.POST('/api/v1/auth/mfa/confirm', {body: {code}}),
  ) as Schema<'MfaRecoveryCodesOutput'>;
  return result.recovery_codes;
}
