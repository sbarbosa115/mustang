import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {ShieldCheck} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {fetchInterventions, interventionsKeys} from '@/entities/intervention';
import {useAuth} from '@/entities/user';
import {useAssignToMe} from '@/features/assign-to-me';
import type {Schema} from '@/shared/api';
import {formatCents} from '@/shared/lib/money';
import {Card, CardContent} from '@/shared/ui/card';
import {Skeleton} from '@/shared/ui/skeleton';
import {DataTable, InterventionItem} from '@/shared/ui/mustang';
import {reasonText} from '../lib/reason';

type Item = Schema<'InterventionOutput'>;
type Summary = Schema<'InterventionSummaryOutput'>;

/**
 * The manager's queue (FR-08): summary tiles (deals that need you, gross at risk, reps with unanswered customers) and
 * one row per deal at risk with its reason, its rep and "Assign to me".
 */
export function InterventionQueue() {
  const {t} = useTranslation('team');
  const {me} = useAuth();
  const assign = useAssignToMe();
  const result = useQuery({
    queryKey: interventionsKeys.list(),
    queryFn: fetchInterventions,
    refetchInterval: 60_000,
  });

  const row = (item: Item) => (
    <InterventionItem
      customerName={item.customer.name}
      dealHref={`/deals/${item.deal.id}`}
      reason={reasonText(t, item)}
      assigneeName={item.assignee?.name}
      atRiskCents={item.at_risk_cents}
      onAssignToMe={
        item.assignee?.id === me?.user.id
          ? undefined
          : () => assign.mutate(item.deal.id)
      }
      assigning={assign.isPending && assign.variables === item.deal.id}
    />
  );
  const columns: Array<ColumnDef<Item, unknown>> = [
    {
      id: 'deal',
      header: t('queue.column'),
      cell: ({row: r}) => row(r.original),
    },
  ];

  return (
    <div className="space-y-4">
      <SummaryTiles summary={result.data?.summary} loading={result.isLoading} />
      <section aria-label={t('queue.label')} className="min-w-0 space-y-3">
        <h2 className="text-lg leading-7 font-semibold">{t('queue.label')}</h2>
        <DataTable
          columns={columns}
          data={result.data?.items}
          isLoading={result.isLoading}
          error={result.error}
          onRetry={() => void result.refetch()}
          empty={{icon: ShieldCheck, title: t('queue.empty')}}
          getRowId={(item) => item.deal.id}
          renderCard={row}
        />
      </section>
    </div>
  );
}

function SummaryTiles({
  summary,
  loading,
}: {
  summary: Summary | undefined;
  loading: boolean;
}) {
  const {t} = useTranslation('team');
  if (loading || !summary) {
    return (
      <div className="grid gap-4 sm:grid-cols-3" aria-busy={loading}>
        {[0, 1, 2].map((i) => (
          <Skeleton key={i} className="h-24 w-full rounded-lg" />
        ))}
      </div>
    );
  }
  return (
    <section
      aria-label={t('summary.label')}
      className="grid gap-4 sm:grid-cols-3"
    >
      <Tile label={t('summary.count')} value={String(summary.count)} />
      <Tile
        label={t('summary.at_risk')}
        value={formatCents(summary.at_risk_cents)}
      />
      <Card className="gap-2 py-4">
        <CardContent className="space-y-2 px-4">
          <p className="text-xs font-medium text-muted-foreground">
            {t('summary.reps')}
          </p>
          {summary.reps_with_unanswered.length === 0 ? (
            <p className="text-sm">{t('summary.reps_none')}</p>
          ) : (
            <ul className="space-y-1 text-sm">
              {summary.reps_with_unanswered.map((rep) => (
                <li
                  key={rep.user.id}
                  className="flex items-baseline justify-between gap-2"
                >
                  <Link
                    to={`/today?user_id=${rep.user.id}`}
                    className="font-medium hover:underline"
                    aria-label={t('summary.rep_link', {name: rep.user.name})}
                  >
                    {rep.user.name}
                  </Link>
                  <span className="tabular inline-flex items-center rounded-full bg-hot-surface px-2 py-0.5 text-xs font-medium text-hot">
                    {t('summary.rep_count', {count: rep.count})}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </section>
  );
}

function Tile({label, value}: {label: string; value: string}) {
  return (
    <Card className="gap-2 py-4">
      <CardContent className="space-y-1 px-4">
        <p className="text-xs font-medium text-muted-foreground">{label}</p>
        <p className="tabular text-3xl leading-9 font-semibold">{value}</p>
      </CardContent>
    </Card>
  );
}
