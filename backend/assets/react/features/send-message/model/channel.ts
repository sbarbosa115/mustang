import type {Channel, ChannelAvailability} from '@/shared/ui/mustang';

/** The channels a message can be sent on from the inbox (a call is placed, not sent). */
export type SendChannel = Extract<Channel, 'text' | 'email'>;
export const SEND_CHANNELS: SendChannel[] = ['text', 'email'];

export interface Contacts {
  phone?: string | null;
  email?: string | null;
}

/** Why a channel cannot be used right now, or null when it can: no contact first, then consent. */
export type Block =
  | {kind: 'no_contact'}
  | {kind: 'consent'; state: Exclude<ChannelAvailability[Channel], 'granted'>};

export function blockOf(
  channel: SendChannel,
  consent: ChannelAvailability,
  contacts: Contacts,
): Block | null {
  const contact = channel === 'text' ? contacts.phone : contacts.email;
  if (!contact) return {kind: 'no_contact'};
  const state = consent[channel];
  return state === 'granted' ? null : {kind: 'consent', state};
}

/** The preferred channel when it is allowed, else the first allowed one, else the preferred (blocked) one. */
export function initialChannel(
  consent: ChannelAvailability,
  contacts: Contacts,
  preferred?: Channel | null,
): SendChannel {
  const wanted: SendChannel =
    preferred === 'email' || preferred === 'text' ? preferred : 'text';
  if (!blockOf(wanted, consent, contacts)) return wanted;
  return SEND_CHANNELS.find((c) => !blockOf(c, consent, contacts)) ?? wanted;
}
