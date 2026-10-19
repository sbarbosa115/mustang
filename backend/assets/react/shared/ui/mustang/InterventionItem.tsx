import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {formatCents} from '../../lib/money';
import {Button} from '../button';

interface Props {
  customerName: string;
  dealHref: string;
  /** Why the deal needs a manager, already worded. */
  reason: string;
  assigneeName?: string | null;
  atRiskCents: number;
  onAssignToMe?: () => void;
  assigning?: boolean;
}

/** A deal at risk, the reason, the assigned rep and "Assign to me" (FR-08). */
export function InterventionItem({
  customerName,
  dealHref,
  reason,
  assigneeName,
  atRiskCents,
  onAssignToMe,
  assigning = false,
}: Props) {
  const {t} = useTranslation('common');
  return (
    <article className="flex flex-col gap-3 rounded-lg border bg-card p-4 sm:flex-row sm:items-center">
      <div className="min-w-0 flex-1 space-y-1">
        <Link to={dealHref} className="font-semibold hover:underline">
          {customerName}
        </Link>
        <p className="text-sm">{reason}</p>
        <p className="text-xs text-muted-foreground">
          {assigneeName
            ? t('intervention.assignee', {name: assigneeName})
            : t('intervention.unassigned')}
          <span aria-hidden> · </span>
          <span className="tabular">
            {t('intervention.at_risk', {amount: formatCents(atRiskCents)})}
          </span>
        </p>
      </div>
      {onAssignToMe && (
        <Button variant="outline" onClick={onAssignToMe} disabled={assigning}>
          {t('intervention.assign_to_me')}
        </Button>
      )}
    </article>
  );
}
