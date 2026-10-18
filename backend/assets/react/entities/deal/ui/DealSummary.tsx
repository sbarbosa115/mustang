import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {formatCents} from '@/shared/lib/money';
import {StageBadge} from '@/shared/ui/mustang';

/** A deal in one line: stage with days in stage, vehicle and assignee. */
export function DealSummary({deal}: {deal: Schema<'DealOutput'>}) {
  const {t} = useTranslation('deals');
  return (
    <div className="flex flex-wrap items-center gap-2 text-sm">
      <StageBadge stage={deal.stage} daysInStage={deal.days_in_stage} />
      <span className="font-medium">
        {deal.vehicle?.title ?? deal.vehicle_text ?? t('summary.no_vehicle')}
      </span>
      {deal.expected_gross_cents != null && (
        <span className="tabular text-muted-foreground">
          {formatCents(deal.expected_gross_cents)}
        </span>
      )}
      <span className="text-muted-foreground">
        {deal.assignee?.name ?? t('summary.unassigned')}
      </span>
    </div>
  );
}
