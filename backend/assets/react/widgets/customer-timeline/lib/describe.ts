import {
  CalendarCheck,
  CalendarClock,
  CalendarX,
  FileText,
  History,
  Inbox,
  ShieldCheck,
  ShieldOff,
  StickyNote,
  TrendingUp,
  UserX,
  type LucideIcon,
} from 'lucide-react';

type Channel = 'text' | 'email' | 'call';

/** How the UI words and draws one timeline entry, from its stable `code` (never from the stored English summary). */
export interface Description {
  /** Key under the `timeline` namespace. */
  key: string;
  params: Record<string, string>;
  icon: LucideIcon;
  channel?: Channel;
  /** The entry's stored summary adds something the wording cannot say. */
  showSummary: boolean;
}

const CHANNELS: Channel[] = ['text', 'email', 'call'];
const FIXED: Record<string, {icon: LucideIcon; showSummary?: boolean}> = {
  lead_captured: {icon: Inbox, showSummary: true},
  appointment_booked: {icon: CalendarClock},
  appointment_rescheduled: {icon: CalendarClock},
  appointment_shown: {icon: CalendarCheck},
  appointment_no_show: {icon: UserX},
  appointment_cancelled: {icon: CalendarX},
  note_added: {icon: StickyNote},
};
const KIND_ICONS: Record<string, LucideIcon> = {
  lead: Inbox,
  message_in: FileText,
  message_out: FileText,
  stage: TrendingUp,
  appointment: CalendarClock,
  note: StickyNote,
  consent: ShieldCheck,
  import: FileText,
};

function channelOf(value: string | undefined): Channel | undefined {
  return CHANNELS.find((c) => c === value);
}

export function describe(
  code: string | null | undefined,
  kind: string,
): Description {
  const fallback: Description = {
    key: `kind.${kind}`,
    params: {},
    icon: KIND_ICONS[kind] ?? History,
    showSummary: true,
  };
  if (!code) return fallback;
  const fixed = FIXED[code];
  if (fixed) {
    return {
      key: `event.${code}`,
      params: {},
      icon: fixed.icon,
      showSummary: fixed.showSummary ?? false,
    };
  }
  const message = /^message_(sent|received)_(.+)$/.exec(code);
  if (message) {
    const channel = channelOf(message[2]);
    if (channel) {
      return {
        key: `event.message_${message[1]}`,
        params: {channel},
        icon: History,
        channel,
        showSummary: false,
      };
    }
  }
  const consent = /^consent_(recorded|revoked)_(.+)$/.exec(code);
  if (consent) {
    const channel = channelOf(consent[2]);
    if (channel) {
      return {
        key: `event.consent_${consent[1]}`,
        params: {channel},
        icon: consent[1] === 'recorded' ? ShieldCheck : ShieldOff,
        showSummary: false,
      };
    }
  }
  const stage = /^stage_changed_(.+)$/.exec(code);
  if (stage) {
    return {
      key: 'event.stage_changed',
      params: {stage: stage[1] ?? ''},
      icon: TrendingUp,
      showSummary: false,
    };
  }
  return fallback;
}
