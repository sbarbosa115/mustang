import type {TFunction} from 'i18next';
import {ApiError} from '@/shared/api';

/** The sentence for a send refusal: its code translated, or the generic one. */
export function sendErrorText(
  t: TFunction<'inbox'>,
  exists: (key: string) => boolean,
  error: unknown,
): string {
  const code = error instanceof ApiError ? error.code : '';
  if (code && exists(`inbox:composer.error.${code}`)) {
    return t(`composer.error.${code}`);
  }
  if (code === 'validation') return t('common:error.validation');
  return t('common:error.generic');
}

/** Refusals after which the thread and the consent state must be read again. */
export function refreshesThread(error: unknown): boolean {
  return (
    error instanceof ApiError &&
    ['consent_missing', 'opted_out', 'contact_missing'].includes(error.code)
  );
}
