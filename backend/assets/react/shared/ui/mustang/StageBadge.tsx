import {useTranslation} from 'react-i18next';
import {cn} from '../../lib/utils';

export type DealStage =
  | 'new'
  | 'engaged'
  | 'appointment'
  | 'visit'
  | 'proposal'
  | 'sold'
  | 'delivered'
  | 'lost';

const STAGE_CLASSES: Record<DealStage, string> = {
  new: 'bg-stage-new-surface text-stage-new',
  engaged: 'bg-stage-engaged-surface text-stage-engaged',
  appointment: 'bg-stage-appointment-surface text-stage-appointment',
  visit: 'bg-stage-visit-surface text-stage-visit',
  proposal: 'bg-stage-proposal-surface text-stage-proposal',
  sold: 'bg-stage-sold-surface text-stage-sold',
  delivered: 'bg-stage-delivered-surface text-stage-delivered',
  lost: 'bg-stage-lost-surface text-stage-lost',
};

interface Props {
  stage: DealStage;
  /** Shown as "· 6d" after the name when given. */
  daysInStage?: number | null;
  className?: string;
}

/** A deal stage as its name in the stage colour; the name is always there, never colour alone. */
export function StageBadge({stage, daysInStage, className}: Props) {
  const {t} = useTranslation('common');
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
        STAGE_CLASSES[stage],
        className,
      )}
    >
      {t(`stage.${stage}`)}
      {daysInStage != null && (
        <span className="tabular opacity-80">
          {t('stage.days_in_stage', {count: daysInStage})}
        </span>
      )}
    </span>
  );
}
