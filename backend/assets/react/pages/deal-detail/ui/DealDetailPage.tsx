import {useQuery} from '@tanstack/react-query';
import {ArrowLeft, Handshake, SearchX} from 'lucide-react';
import {useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {dealsKeys, fetchDeal, type Deal} from '@/entities/deal';
import {AssignDealDialog} from '@/features/assign-deal';
import {AttachVehicleDialog} from '@/features/attach-vehicle';
import {StageDialog} from '@/features/change-deal-stage';
import {TradeInDialog} from '@/features/set-trade-in';
import {ApiError} from '@/shared/api';
import {formatDateTime} from '@/shared/lib/dates';
import {formatCents, formatMileage} from '@/shared/lib/money';
import {Button} from '@/shared/ui/button';
import {Card, CardContent} from '@/shared/ui/card';
import {
  EmptyState,
  RelativeTime,
  StageBadge,
  type DealStage,
} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';

type Dialog = 'stage' | 'vehicle' | 'trade_in' | 'assign' | null;

const PROGRESS: Array<{stage: DealStage; at: (d: Deal) => string | null}> = [
  {stage: 'new', at: (d) => d.created_at},
  {stage: 'engaged', at: (d) => d.engaged_at},
  {stage: 'appointment', at: (d) => d.appointment_at},
  {stage: 'visit', at: (d) => d.visited_at},
  {stage: 'proposal', at: (d) => d.proposal_at},
  {stage: 'sold', at: (d) => d.sold_at},
  {stage: 'delivered', at: (d) => d.delivered_at},
];

/** One deal: its stage and how it got there, the vehicle of interest, the trade-in and who works it. */
export function DealDetailPage() {
  const {t} = useTranslation('deals');
  const {dealId = ''} = useParams();
  const [dialog, setDialog] = useState<Dialog>(null);
  const deal = useQuery({
    queryKey: dealsKeys.detail(dealId),
    queryFn: () => fetchDeal(dealId),
    retry: (count, error) => !(error instanceof ApiError) && count < 2,
  });

  const back = (
    <Button variant="ghost" size="sm" asChild className="-ml-2">
      <Link to="/deals">
        <ArrowLeft aria-hidden />
        {t('detail.back')}
      </Link>
    </Button>
  );

  if (deal.isPending) {
    return (
      <>
        <PageHeader title={t('title')} back={back} />
        <Card aria-busy="true" aria-label={t('detail.loading')}>
          <CardContent className="space-y-3">
            <Skeleton className="h-6 w-1/2" />
            <Skeleton className="h-4 w-1/3" />
            <Skeleton className="h-24 w-full" />
          </CardContent>
        </Card>
      </>
    );
  }
  if (deal.isError) {
    const missing = deal.error instanceof ApiError && deal.error.status === 404;
    return (
      <>
        <PageHeader title={t('title')} back={back} />
        <Card>
          <EmptyState
            icon={missing ? SearchX : Handshake}
            title={missing ? t('detail.not_found') : t('detail.error')}
            action={
              missing ? (
                <Button variant="outline" asChild>
                  <Link to="/deals">{t('detail.back')}</Link>
                </Button>
              ) : (
                <Button variant="outline" onClick={() => void deal.refetch()}>
                  {t('detail.retry')}
                </Button>
              )
            }
          />
        </Card>
      </>
    );
  }

  const d = deal.data;
  const close = (open: boolean) => {
    if (!open) setDialog(null);
  };
  const tradeIn = d.trade_in;
  return (
    <>
      <PageHeader
        title={d.customer.name}
        back={back}
        actions={
          <Button onClick={() => setDialog('stage')}>
            {d.stage === 'lost' ? t('detail.reopen') : t('detail.move')}
          </Button>
        }
      />
      <div className="mb-4">
        <StageBadge stage={d.stage} daysInStage={d.days_in_stage} />
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div className="space-y-4">
          <Section
            title={t('detail.vehicle')}
            action={
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDialog('vehicle')}
              >
                {t('detail.edit_vehicle')}
              </Button>
            }
          >
            {d.vehicle ? (
              <p>
                <Link
                  to={`/inventory/${d.vehicle.id}`}
                  className="font-medium underline-offset-4 hover:underline"
                  aria-label={t('detail.open_vehicle', {
                    title: d.vehicle.title,
                  })}
                >
                  {d.vehicle.title}
                </Link>{' '}
                <span className="font-mono text-sm text-muted-foreground">
                  {d.vehicle.stock_number}
                </span>
              </p>
            ) : null}
            {d.vehicle_text && <p>{d.vehicle_text}</p>}
            {!d.vehicle && !d.vehicle_text && (
              <p className="text-muted-foreground">{t('summary.no_vehicle')}</p>
            )}
          </Section>

          <Section
            title={t('detail.trade_in')}
            action={
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDialog('trade_in')}
              >
                {t('detail.edit_trade_in')}
              </Button>
            }
          >
            {tradeIn ? (
              <div className="space-y-1">
                <p className="font-medium">
                  {[tradeIn.year, tradeIn.make, tradeIn.model]
                    .filter((v) => v != null && v !== '')
                    .join(' ')}
                </p>
                <p className="flex flex-wrap gap-x-3 text-sm text-muted-foreground">
                  {tradeIn.mileage != null && (
                    <span className="tabular">
                      {formatMileage(tradeIn.mileage)}
                    </span>
                  )}
                  {tradeIn.payoff_cents != null && (
                    <span className="tabular">
                      {t('detail.payoff', {
                        amount: formatCents(tradeIn.payoff_cents),
                      })}
                    </span>
                  )}
                </p>
              </div>
            ) : (
              <p className="text-muted-foreground">{t('detail.no_trade_in')}</p>
            )}
          </Section>

          <Section title={t('detail.progress')}>
            <ol className="space-y-2">
              {PROGRESS.map(({stage, at}) => {
                const when = at(d);
                return (
                  <li
                    key={stage}
                    className="flex items-center justify-between gap-3 text-sm"
                  >
                    <StageBadge stage={stage} />
                    {when ? (
                      <span className="tabular text-muted-foreground">
                        {formatDateTime(when)}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">
                        {t('detail.not_reached')}
                      </span>
                    )}
                  </li>
                );
              })}
              {d.lost_at && (
                <li className="flex items-center justify-between gap-3 text-sm">
                  <StageBadge stage="lost" />
                  <span className="tabular text-muted-foreground">
                    {formatDateTime(d.lost_at)}
                  </span>
                </li>
              )}
            </ol>
          </Section>
        </div>

        <Card>
          <CardContent>
            <dl className="space-y-4 text-sm">
              <Row label={t('detail.customer')}>
                <Link
                  to={`/customers/${d.customer.id}`}
                  className="font-medium underline-offset-4 hover:underline"
                  aria-label={t('detail.open_customer', {
                    name: d.customer.name,
                  })}
                >
                  {d.customer.name}
                </Link>
              </Row>
              <Row label={t('detail.assignee')}>
                <span className="flex items-center justify-between gap-2">
                  <span>{d.assignee?.name ?? t('summary.unassigned')}</span>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setDialog('assign')}
                  >
                    {t('detail.change_assignee')}
                  </Button>
                </span>
              </Row>
              {d.lost_reason && (
                <Row label={t('detail.lost_reason')}>{d.lost_reason}</Row>
              )}
              <Row label={t('detail.expected_gross')}>
                <span className="tabular">
                  {d.expected_gross_cents != null
                    ? formatCents(d.expected_gross_cents)
                    : t('detail.not_set')}
                </span>
              </Row>
              {d.lead_id && (
                <Row label={t('detail.lead')}>
                  <Link
                    to={`/leads/${d.lead_id}`}
                    className="underline-offset-4 hover:underline"
                  >
                    {t('detail.open_lead')}
                  </Link>
                </Row>
              )}
              <Row label={t('detail.opened')}>
                <RelativeTime iso={d.created_at} />
              </Row>
              {d.last_activity_at && (
                <Row label={t('detail.last_activity')}>
                  <RelativeTime iso={d.last_activity_at} />
                </Row>
              )}
            </dl>
          </CardContent>
        </Card>
      </div>

      <StageDialog deal={d} open={dialog === 'stage'} onOpenChange={close} />
      <AttachVehicleDialog
        deal={d}
        open={dialog === 'vehicle'}
        onOpenChange={close}
      />
      <TradeInDialog
        deal={d}
        open={dialog === 'trade_in'}
        onOpenChange={close}
      />
      <AssignDealDialog
        deal={d}
        open={dialog === 'assign'}
        onOpenChange={close}
      />
    </>
  );
}

function Section({
  title,
  action,
  children,
}: {
  title: string;
  action?: ReactNode;
  children: ReactNode;
}) {
  return (
    <Card>
      <CardContent className="space-y-3">
        <div className="flex items-center justify-between gap-2">
          <h2 className="text-sm font-medium">{title}</h2>
          {action}
        </div>
        {children}
      </CardContent>
    </Card>
  );
}

function Row({label, children}: {label: string; children: ReactNode}) {
  return (
    <div className="space-y-1">
      <dt className="text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  );
}
