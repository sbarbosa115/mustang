import {ApiError} from '@/shared/api';

const KNOWN = [
  'mfa_invalid_code',
  'mfa_code_reused',
  'mfa_too_many_attempts',
  'mfa_already_enrolled',
  'mfa_not_enrolled',
  'unauthenticated',
  'validation',
] as const;

/** The auth.mfa.error.* key for a refusal from the MFA endpoints. */
export function mfaErrorKey(error: unknown): string {
  const code = error instanceof ApiError ? error.code : 'generic';
  return `mfa.error.${(KNOWN as readonly string[]).includes(code) ? code : 'generic'}`;
}
