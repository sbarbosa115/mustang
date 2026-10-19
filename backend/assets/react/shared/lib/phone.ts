import {parsePhoneNumberFromString} from 'libphonenumber-js';

/** Shows an E.164 number the way people read it: +18135551001 → (813) 555-1001. */
export function formatPhone(e164: string | null | undefined): string {
  if (!e164) return '';
  const parsed = parsePhoneNumberFromString(e164);
  if (!parsed) return e164;
  return parsed.country === 'US' || parsed.country === 'CA'
    ? parsed.formatNational()
    : parsed.formatInternational();
}

/** The tel: link for a number, for one-tap calling on phones. */
export function telHref(e164: string): string {
  return `tel:${e164}`;
}
