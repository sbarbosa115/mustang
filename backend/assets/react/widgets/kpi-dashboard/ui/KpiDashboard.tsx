import {useQuery} from '@tanstack/react-query';
import {AlertTriangle} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {fetchKpis, kpisKeys} from '@/entities/kpi';
import type {Schema} from '@/shared/api';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {EmptyState, KpiTile} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';
import {changeOf, formatChange, formatNumber} from '../model/format';
import {FunnelChart} from './FunnelChart';

type Kpi = Schema<'KpiOutput'>;

interface Props {
  /** Dates (yyyy-MM-dd, inclusive); both left out is the last 30 days. */
  from?: string;
  to?: string;
}

/** The seven KPIs as tiles with their change against the previous period, and the funnel from lead to sale. */
export function KpiDashboard({from, to}: Props) {
  const {t} = useTranslation('reports');
  const query = useQuery({
    queryKey: kpisKeys.list({from, to}),
    queryFn: () => fetchKpis({from, to}),
  });

  if (query.isLoading) {
    return (
      <div aria-label={t('loading')} aria-busy="true" className="space-y-4">
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          {Array.from({length: 7}, (_, i) => (
            <Skeleton key={i} className="h-28" />
          ))}
        </div>
        <Skeleton className="h-72" />
      </div>
    );
  }
  if (query.isError || !query.data) {
    const invalid =
      query.error instanceof ApiError && query.error.code === 'invalid_period';
    return (
      <Card>
        <EmptyState
          icon={AlertTriangle}
          title={invalid ? t('error.invalid') : t('error.title')}
          action={
            <Button variant="outline" onClick={() => void query.refetch()}>
              {t('error.retry')}
            </Button>
          }
        />
      </Card>
    );
  }

  const report = query.data;
  return (
    <div className="space-y-6">
      <p className="text-sm text-muted-foreground">
        {t('period.range', {from: report.period.from, to: report.period.to})}
        {' · '}
        {t('period.versus', {
          from: report.previous.from,
          to: report.previous.to,
        })}
      </p>
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {report.kpis.map((kpi) => (
          <Tile key={kpi.code} kpi={kpi} />
        ))}
      </div>
      <FunnelChart steps={report.funnel} />
      <details className="rounded-lg border p-4 text-sm">
        <summary className="cursor-pointer font-medium">
          {t('definitions.title')}
        </summary>
        <dl className="mt-3 space-y-2">
          {report.kpis.map((kpi) => (
            <div key={kpi.code}>
              <dt className="font-medium">{t(`kpi.${kpi.code}`)}</dt>
              <dd className="text-muted-foreground">
                {t(`definitions.${kpi.code}`)}
              </dd>
            </div>
          ))}
        </dl>
      </details>
    </div>
  );
}

function Tile({kpi}: {kpi: Kpi}) {
  const {t} = useTranslation('reports');
  const fmt = (v: number) => t(`value.${kpi.unit}`, {value: formatNumber(v)});
  const change = changeOf(kpi.value, kpi.previous_value);
  return (
    <KpiTile
      label={t(`kpi.${kpi.code}`)}
      value={kpi.value == null ? t('kpi.noData') : fmt(kpi.value)}
      delta={change}
      deltaText={
        change == null || kpi.previous_value == null
          ? t('delta.none')
          : t(`delta.${kpi.unit}`, {
              change: formatChange(change),
              previous: fmt(kpi.previous_value),
            })
      }
      higherIsBetter={kpi.code !== 'first_response_minutes'}
    />
  );
}
