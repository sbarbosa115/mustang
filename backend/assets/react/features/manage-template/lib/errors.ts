import type {TFunction} from 'i18next';
import {ApiError} from '@/shared/api';

/** The sentence for an API refusal: its code translated, or the generic one. */
export function errorText(
  t: TFunction<'templates'>,
  exists: (key: string) => boolean,
  error: unknown,
): string {
  const code = error instanceof ApiError ? error.code : '';
  return code && exists(`templates:error.${code}`)
    ? t(`error.${code}`)
    : t('common:error.generic');
}
