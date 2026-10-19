import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {useAuth} from '@/entities/user';
import {
  DEFAULT_DAYS,
  PeriodPicker,
  isValidRange,
  lastDays,
  todayIn,
  type Range,
} from '@/features/pick-period';
import {PageHeader} from '@/shared/ui/page';
import {KpiDashboard} from '@/widgets/kpi-dashboard';

/** /dashboard (managers): the period lives in the address (?from=&to=), the last 30 days when it is empty. */
export function DashboardPage() {
  const {t} = useTranslation('reports');
  const {me} = useAuth();
  const [params, setParams] = useSearchParams();
  const today = todayIn(me?.dealership.timezone ?? 'America/New_York');
  const from = params.get('from') ?? '';
  const to = params.get('to') ?? '';
  const range: Range = isValidRange(from, to)
    ? {from, to}
    : lastDays(DEFAULT_DAYS, today);

  const choose = (next: Range) =>
    setParams({from: next.from, to: next.to}, {replace: true});

  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      <div className="space-y-6">
        <PeriodPicker value={range} today={today} onChange={choose} />
        <KpiDashboard from={range.from} to={range.to} />
      </div>
    </>
  );
}
