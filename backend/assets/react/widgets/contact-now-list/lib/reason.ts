import type {TFunction} from 'i18next';
import type {Schema} from '@/shared/api';
import {formatTime} from '@/shared/lib/dates';

type Item = Schema<'WorklistItemOutput'>;

const num = (value: unknown): number =>
  typeof value === 'number' ? value : Number(value ?? 0);
const str = (value: unknown): string =>
  typeof value === 'string' ? value : '';

/** "12 min", "2 hours", "3 days": the largest whole unit of a wait. */
export function waitText(t: TFunction<'today'>, minutes: number): string {
  if (minutes < 60) return t('wait.minutes', {count: minutes});
  if (minutes < 24 * 60)
    return t('wait.hours', {count: Math.floor(minutes / 60)});
  return t('wait.days', {count: Math.floor(minutes / (24 * 60))});
}

/** The row's reason in words, from its code and params (the API sends no prose). */
export function reasonText(
  t: TFunction<'today'>,
  item: Pick<Item, 'reason_code' | 'reason_params'>,
  timeZone?: string,
): string {
  const p = item.reason_params;
  switch (item.reason_code) {
    case 'unanswered_inbound':
      return t(
        p.question
          ? 'reason.unanswered_inbound_question'
          : 'reason.unanswered_inbound',
        {wait: waitText(t, num(p.minutes))},
      );
    case 'new_lead_uncontacted':
      return str(p.vehicle)
        ? t('reason.new_lead_uncontacted_vehicle', {
            wait: waitText(t, num(p.minutes)),
            vehicle: str(p.vehicle),
          })
        : t('reason.new_lead_uncontacted', {wait: waitText(t, num(p.minutes))});
    case 'appointment_today_unconfirmed':
    case 'no_show_without_follow_up':
      return t(`reason.${item.reason_code}`, {
        time: formatTime(str(p.scheduled_at), timeZone),
        kind: t(`kind.${str(p.kind) || 'visit'}`),
      });
    case 'deal_idle':
      return t('reason.deal_idle', {
        wait: waitText(t, num(p.days) * 24 * 60),
        stage: t(`stage.${str(p.stage) || 'engaged'}`),
      });
  }
}
