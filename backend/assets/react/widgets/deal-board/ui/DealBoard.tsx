import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {ArrowRightLeft, Handshake, SearchX} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useSearchParams} from 'react-router';
import {dealsKeys, listDeals, type Deal} from '@/entities/deal';
import {StageDialog} from '@/features/change-deal-stage';
import {cn} from '@/shared/lib/utils';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {EmptyState, Pagination, type DealStage} from '@/shared/ui/mustang';
import {RadioGroup, RadioGroupItem} from '@/shared/ui/radio-group';
import {Skeleton} from '@/shared/ui/skeleton';

const OPEN_STAGES: DealStage[] = [
  'new',
  'engaged',
  'appointment',
  'visit',
  'proposal',
  'sold',
];
const VIEWS = ['open', 'delivered', 'lost'] as const;
type View = (typeof VIEWS)[number];
const BOARD_SIZE = 100;
const CLOSED_PAGE = 25;

interface Props {
  /** The signed-in user, for "My deals". */
  userId?: string;
}

/** Open deals in a column per stage (stacked on phones); delivered and lost deals as a paged list. */
export function DealBoard({userId}: Props) {
  const {t} = useTranslation('deals');
  const [params, setParams] = useSearchParams();
  const view: View = VIEWS.find((v) => v === params.get('view')) ?? 'open';
  const mine = params.get('scope') === 'mine' && !!userId;
  const term = params.get('q') ?? '';
  const pageNo = Math.max(1, Number(params.get('page')) || 1);
  const [moving, setMoving] = useState<Deal | null>(null);

  const set = (changes: Record<string, string | null>) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        for (const [key, value] of Object.entries(changes)) {
          if (value === null || value === '') next.delete(key);
          else next.set(key, value);
        }
        return next;
      },
      {replace: true},
    );

  const query = {
    ...(view === 'open'
      ? {open: true, per_page: BOARD_SIZE}
      : {stage: view, per_page: CLOSED_PAGE, page: pageNo}),
    ...(term.trim() ? {q: term.trim()} : {}),
    ...(mine ? {assigned_to: userId} : {}),
  };
  const deals = useQuery({
    queryKey: dealsKeys.list(query),
    queryFn: () => listDeals(query),
    placeholderData: keepPreviousData,
  });
  const filtered = term.trim() !== '' || mine;

  const segmented = (
    name: string,
    label: string,
    value: string,
    options: Array<{value: string; label: string}>,
    onChange: (value: string) => void,
  ) => (
    <fieldset>
      <legend className="sr-only">{label}</legend>
      <RadioGroup
        value={value}
        onValueChange={onChange}
        className="flex flex-wrap gap-1 rounded-lg bg-muted p-1"
      >
        {options.map((o) => (
          <Label
            key={o.value}
            htmlFor={`${name}_${o.value}`}
            className="flex min-h-9 cursor-pointer items-center rounded-md px-3 text-sm font-medium text-muted-foreground has-[[data-state=checked]]:bg-background has-[[data-state=checked]]:text-foreground has-[[data-state=checked]]:shadow-sm"
          >
            <RadioGroupItem
              id={`${name}_${o.value}`}
              value={o.value}
              className="sr-only"
            />
            {o.label}
          </Label>
        ))}
      </RadioGroup>
    </fieldset>
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
        <div className="lg:w-80">
          <Label htmlFor="deal_search" className="sr-only">
            {t('board.search')}
          </Label>
          <Input
            id="deal_search"
            type="search"
            value={term}
            placeholder={t('board.search')}
            onChange={(e) => set({q: e.target.value, page: null})}
          />
        </div>
        <div className="flex flex-wrap gap-2">
          {segmented(
            'deal_view',
            t('board.view.label'),
            view,
            VIEWS.map((v) => ({value: v, label: t(`board.view.${v}`)})),
            (v) => set({view: v === 'open' ? null : v, page: null}),
          )}
          {userId &&
            segmented(
              'deal_scope',
              t('board.scope.label'),
              mine ? 'mine' : 'all',
              [
                {value: 'all', label: t('board.scope.all')},
                {value: 'mine', label: t('board.scope.mine')},
              ],
              (v) => set({scope: v === 'mine' ? 'mine' : null, page: null}),
            )}
        </div>
      </div>

      {deals.isPending ? (
        <div
          aria-busy="true"
          aria-label={t('board.loading')}
          className="grid gap-3 md:grid-cols-3 xl:grid-cols-6"
        >
          {OPEN_STAGES.map((s) => (
            <Skeleton key={s} className="h-40 w-full" />
          ))}
        </div>
      ) : deals.isError ? (
        <Card>
          <EmptyState
            icon={Handshake}
            title={t('board.error')}
            action={
              <Button variant="outline" onClick={() => void deals.refetch()}>
                {t('common:action.retry')}
              </Button>
            }
          />
        </Card>
      ) : deals.data.items.length === 0 ? (
        <Card>
          {filtered ? (
            <EmptyState
              icon={SearchX}
              title={t('board.filtered_empty')}
              action={
                <Button
                  variant="outline"
                  onClick={() => set({q: null, scope: null, page: null})}
                >
                  {t('common:list.show_all')}
                </Button>
              }
            />
          ) : view === 'open' ? (
            <EmptyState
              icon={Handshake}
              title={t('board.empty')}
              description={t('board.empty_help')}
            />
          ) : (
            <EmptyState icon={Handshake} title={t('board.closed_empty')} />
          )}
        </Card>
      ) : view === 'open' ? (
        <>
          <div
            role="group"
            aria-label={t('board.label')}
            className="flex flex-col gap-4 md:flex-row md:overflow-x-auto md:pb-2"
          >
            {OPEN_STAGES.map((stage) => {
              const items = deals.data.items.filter((d) => d.stage === stage);
              const title = t('board.column', {
                stage: t(`common:stage.${stage}`),
                count: items.length,
              });
              return (
                <section
                  key={stage}
                  role="region"
                  aria-label={title}
                  className="flex flex-col gap-2 md:w-64 md:shrink-0"
                >
                  <h2 className="flex items-center justify-between px-1 text-sm font-medium">
                    <span>{t(`common:stage.${stage}`)}</span>
                    <span className="tabular text-muted-foreground">
                      {items.length}
                    </span>
                  </h2>
                  <div
                    className={cn(
                      'flex flex-1 flex-col gap-2 rounded-lg bg-muted/50 p-2',
                      items.length === 0 && 'justify-center',
                    )}
                  >
                    {items.length === 0 ? (
                      <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                        {t('board.column_empty')}
                      </p>
                    ) : (
                      items.map((d) => (
                        <DealCard key={d.id} deal={d} onMove={setMoving} />
                      ))
                    )}
                  </div>
                </section>
              );
            })}
          </div>
          {deals.data.total > deals.data.items.length && (
            <p className="text-sm text-muted-foreground">
              {t('board.truncated', {
                shown: deals.data.items.length,
                total: deals.data.total,
              })}
            </p>
          )}
        </>
      ) : (
        <>
          <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
            {deals.data.items.map((d) => (
              <DealCard key={d.id} deal={d} onMove={setMoving} />
            ))}
          </div>
          <Pagination
            page={pageNo}
            perPage={CLOSED_PAGE}
            total={deals.data.total}
            onPage={(p) => set({page: String(p)})}
          />
        </>
      )}

      {moving && (
        <StageDialog
          deal={moving}
          open
          onOpenChange={(open) => {
            if (!open) setMoving(null);
          }}
        />
      )}
    </div>
  );
}

function DealCard({deal, onMove}: {deal: Deal; onMove: (deal: Deal) => void}) {
  const {t} = useTranslation('deals');
  const name = deal.customer.name;
  return (
    <Card className="relative gap-1 p-3 text-sm shadow-none">
      <div className="flex items-start justify-between gap-2">
        <Link
          to={`/deals/${deal.id}`}
          aria-label={t('card.open', {name})}
          className="font-medium after:absolute after:inset-0 after:content-['']"
        >
          {name}
        </Link>
        <Button
          variant="ghost"
          size="icon"
          className="relative z-10 -mt-1 -mr-1"
          aria-label={t('card.move', {name})}
          onClick={() => onMove(deal)}
        >
          <ArrowRightLeft aria-hidden />
        </Button>
      </div>
      <p className="truncate">
        {deal.vehicle?.title ?? deal.vehicle_text ?? (
          <span className="text-muted-foreground">
            {t('summary.no_vehicle')}
          </span>
        )}
      </p>
      {deal.stage === 'lost' && deal.lost_reason && (
        <p className="text-muted-foreground">
          {t('card.lost_reason', {reason: deal.lost_reason})}
        </p>
      )}
      <div className="flex flex-wrap items-center justify-between gap-x-2 text-xs text-muted-foreground">
        <span className="tabular">
          {t('card.days_in_stage', {count: deal.days_in_stage})}
        </span>
        <span>{deal.assignee?.name ?? t('summary.unassigned')}</span>
      </div>
    </Card>
  );
}
