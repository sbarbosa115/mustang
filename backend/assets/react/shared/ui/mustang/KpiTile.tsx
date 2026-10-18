import {ArrowDownRight, ArrowUpRight, Minus} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {cn} from '../../lib/utils';
import {Card, CardContent} from '../card';

interface Props {
  label: string;
  /** Already formatted for its unit ("12 min", "64%", "3.5"). */
  value: string;
  /** The change versus the previous period, in the KPI's own unit; null when there is nothing to compare. */
  delta: number | null;
  deltaText?: string;
  /** False for KPIs where lower is better (response time). */
  higherIsBetter?: boolean;
}

/** Label, a Display-size number, and the change versus the previous period with an arrow and words (DESIGN.md). */
export function KpiTile({
  label,
  value,
  delta,
  deltaText,
  higherIsBetter = true,
}: Props) {
  const {t} = useTranslation('common');
  const direction =
    delta == null || delta === 0 ? 'flat' : delta > 0 ? 'up' : 'down';
  const good =
    direction === 'flat' ? null : (direction === 'up') === higherIsBetter;
  const Icon =
    direction === 'up'
      ? ArrowUpRight
      : direction === 'down'
        ? ArrowDownRight
        : Minus;
  return (
    <Card className="gap-2 py-4">
      <CardContent className="space-y-1 px-4">
        <p className="text-xs font-medium text-muted-foreground">{label}</p>
        <p className="tabular text-3xl leading-9 font-semibold">{value}</p>
        <p
          className={cn(
            'flex items-center gap-1 text-xs',
            good === true && 'text-success',
            good === false && 'text-danger',
            good === null && 'text-muted-foreground',
          )}
        >
          <Icon aria-hidden className="size-3.5" />
          <span>{deltaText ?? t(`kpi.delta.${direction}`)}</span>
        </p>
      </CardContent>
    </Card>
  );
}
