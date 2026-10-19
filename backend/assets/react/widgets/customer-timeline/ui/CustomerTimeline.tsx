import {keepPreviousData, useQuery} from '@tanstack/react-query';
import {History} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {fetchTimeline, timelineKeys} from '@/entities/activity';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {
  ChannelIcon,
  EmptyState,
  Pagination,
  RelativeTime,
} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';
import {describe} from '../lib/describe';
import {dayLabel, groupByDay} from '../lib/groupByDay';

const PER_PAGE = 25;

/** Everything that happened with a customer, newest first, grouped by day (FR-04). */
export function CustomerTimeline({customerId}: {customerId: string}) {
  const {t} = useTranslation('timeline');
  const [page, setPage] = useState(1);
  const query = {page, per_page: PER_PAGE};
  const timeline = useQuery({
    queryKey: [...timelineKeys.list({customerId, ...query})],
    queryFn: () => fetchTimeline(customerId, query),
    placeholderData: keepPreviousData,
  });

  if (timeline.isLoading) {
    return (
      <Card
        className="gap-3 p-4"
        aria-busy="true"
        aria-label={t('loading')}
        role="status"
      >
        <Skeleton className="h-5 w-32" />
        <Skeleton className="h-12 w-full" />
        <Skeleton className="h-12 w-full" />
        <Skeleton className="h-12 w-full" />
      </Card>
    );
  }
  if (timeline.isError || !timeline.data) {
    return (
      <Card>
        <EmptyState
          icon={History}
          title={t('error')}
          action={
            <Button variant="outline" onClick={() => void timeline.refetch()}>
              {t('common:action.retry')}
            </Button>
          }
        />
      </Card>
    );
  }
  const {items, total} = timeline.data;
  if (items.length === 0) {
    return (
      <Card>
        <EmptyState
          icon={History}
          title={t('empty')}
          description={t('empty_hint')}
        />
      </Card>
    );
  }

  const now = new Date();
  return (
    <div className="flex flex-col gap-4" data-testid="customer-timeline">
      {groupByDay(items).map((group) => {
        const label = dayLabel(group.day, now);
        const heading = label.kind === 'date' ? label.text : t(label.kind);
        return (
          <section key={group.day} aria-label={heading}>
            <h3 className="mb-2 text-sm font-medium text-muted-foreground">
              {heading}
            </h3>
            <Card className="gap-0 py-0">
              <ul className="divide-y">
                {group.items.map((entry) => {
                  const d = describe(entry.code, entry.kind);
                  const Icon = d.icon;
                  const params: Record<string, string> = {...d.params};
                  if (params.channel)
                    params.channel = t(`channel.${params.channel}`);
                  if (params.stage)
                    params.stage = t(`common:stage.${params.stage}`);
                  const headline = t(d.key, params);
                  const href =
                    entry.ref_type === 'deal' && entry.ref_id
                      ? `/deals/${entry.ref_id}`
                      : entry.ref_type === 'lead' && entry.ref_id
                        ? `/leads/${entry.ref_id}`
                        : null;
                  return (
                    <li
                      key={entry.id}
                      className="flex items-start gap-3 p-4 text-sm"
                    >
                      <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                        {d.channel ? (
                          <ChannelIcon channel={d.channel} />
                        ) : (
                          <Icon
                            aria-hidden
                            className="size-4 text-muted-foreground"
                          />
                        )}
                      </span>
                      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                        <p className="font-medium">
                          {href ? (
                            <Link to={href} className="hover:underline">
                              {headline}
                            </Link>
                          ) : (
                            headline
                          )}
                          {entry.actor && (
                            <span className="font-normal text-muted-foreground">
                              {' '}
                              {t('by', {name: entry.actor.name})}
                            </span>
                          )}
                        </p>
                        {d.showSummary && entry.summary && (
                          <p className="text-muted-foreground">
                            {entry.summary}
                          </p>
                        )}
                      </div>
                      <RelativeTime
                        iso={entry.occurred_at}
                        className="tabular shrink-0 text-muted-foreground"
                      />
                    </li>
                  );
                })}
              </ul>
            </Card>
          </section>
        );
      })}
      <Pagination
        page={page}
        perPage={PER_PAGE}
        total={total}
        onPage={setPage}
      />
    </div>
  );
}
