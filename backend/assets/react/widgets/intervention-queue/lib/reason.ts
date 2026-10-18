import type {TFunction} from 'i18next';
import type {Schema} from '@/shared/api';

type Item = Schema<'InterventionOutput'>;

const num = (value: unknown): number =>
  typeof value === 'number' ? value : Number(value ?? 0);

/** "45 min", "2 hours", "3 days": the largest whole unit of a wait. */
export function waitText(t: TFunction<'team'>, minutes: number): string {
  if (minutes < 60) return t('wait.minutes', {count: minutes});
  if (minutes < 24 * 60)
    return t('wait.hours', {count: Math.floor(minutes / 60)});
  return t('wait.days', {count: Math.floor(minutes / (24 * 60))});
}

/** Why the deal needs a manager, in words, from its code and params. */
export function reasonText(
  t: TFunction<'team'>,
  item: Pick<Item, 'reason_code' | 'reason_params'>,
): string {
  const p = item.reason_params;
  switch (item.reason_code) {
    case 'customer_unanswered':
      return t('reason.customer_unanswered', {
        wait: waitText(t, num(p.minutes)),
      });
    case 'lead_unattended':
      return t(
        p.unassigned
          ? 'reason.lead_unattended_unassigned'
          : 'reason.lead_unattended',
        {wait: waitText(t, num(p.minutes))},
      );
    case 'no_show_without_follow_up':
      return t('reason.no_show_without_follow_up', {
        wait: waitText(t, num(p.hours) * 60),
      });
    case 'deal_idle':
      return t('reason.deal_idle', {
        wait: waitText(t, num(p.days) * 24 * 60),
        stage: t(`stage.${typeof p.stage === 'string' ? p.stage : 'engaged'}`),
      });
  }
}
