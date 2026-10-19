import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Search, ShieldCheck} from 'lucide-react';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {
  consentsKeys,
  listSuppressions,
  type SuppressionPage,
} from '@/entities/consent';
import {SettingsTabs} from '@/widgets/settings-nav';
import {formatPhone} from '@/shared/lib/phone';
import {Card} from '@/shared/ui/card';
import {Input} from '@/shared/ui/input';
import {
  ChannelIcon,
  DataTable,
  Pagination,
  RelativeTime,
} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';

type Row = SuppressionPage['items'][number];
const PER_PAGE = 25;

/** /settings/suppressions: contacts that opted out (global, FR-12), searchable and paged; the search lives in the URL. */
export function SettingsSuppressionsPage() {
  const {t, i18n} = useTranslation('consent');
  const [params, setParams] = useSearchParams();
  const urlQuery = params.get('q') ?? '';
  const page = Math.max(1, Number(params.get('page')) || 1);
  const [text, setText] = useState(urlQuery);

  useEffect(() => {
    const timer = setTimeout(() => {
      const next = text.trim();
      if (next === urlQuery) return;
      setParams(next ? {q: next} : {}, {replace: true});
    }, 300);
    return () => clearTimeout(timer);
  }, [text, urlQuery, setParams]);

  const query = {page, per_page: PER_PAGE, ...(urlQuery ? {q: urlQuery} : {})};
  const result = useQuery({
    queryKey: [...consentsKeys.all, 'suppressions', query],
    queryFn: () => listSuppressions(query),
    placeholderData: (previous) => previous,
  });
  const show = (row: Row) =>
    row.channel === 'email'
      ? row.contact_value
      : formatPhone(row.contact_value);
  const reason = (row: Row) =>
    i18n.exists(`consent:suppressions.reasons.${row.reason}`)
      ? t(`suppressions.reasons.${row.reason}`)
      : row.reason;

  const columns: Array<ColumnDef<Row, unknown>> = [
    {
      id: 'contact',
      header: t('column.contact'),
      cell: ({row}) => (
        <span className="tabular font-medium">{show(row.original)}</span>
      ),
    },
    {
      id: 'channel',
      header: t('column.channel'),
      cell: ({row}) => (
        <span className="flex items-center gap-2">
          <ChannelIcon channel={row.original.channel} />
          {t(`common:channel.${row.original.channel}`)}
        </span>
      ),
    },
    {
      id: 'reason',
      header: t('suppressions.reason'),
      cell: ({row}) => reason(row.original),
    },
    {
      id: 'since',
      header: t('suppressions.since'),
      cell: ({row}) => (
        <RelativeTime
          iso={row.original.created_at}
          className="text-muted-foreground"
        />
      ),
    },
  ];

  return (
    <>
      <PageHeader title={t('settings:title')} />
      <SettingsTabs />
      <div className="space-y-4">
        <div className="space-y-1">
          <h2 className="text-lg font-semibold">{t('suppressions.title')}</h2>
          <p className="text-sm text-muted-foreground">
            {t('suppressions.description')}
          </p>
        </div>
        <div className="relative">
          <Search
            aria-hidden
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
          />
          <label htmlFor="suppression_search" className="sr-only">
            {t('suppressions.search_label')}
          </label>
          <Input
            id="suppression_search"
            type="search"
            className="pl-9 sm:max-w-sm"
            value={text}
            placeholder={t('suppressions.search_placeholder')}
            onChange={(event) => setText(event.target.value)}
          />
        </div>
        <DataTable
          columns={columns}
          data={result.data?.items}
          isLoading={result.isLoading}
          error={result.error}
          onRetry={() => void result.refetch()}
          filtered={urlQuery !== ''}
          onClearFilters={() => {
            setText('');
            setParams({}, {replace: true});
          }}
          getRowId={(row) => row.id}
          empty={{icon: ShieldCheck, title: t('suppressions.empty')}}
          renderCard={(row) => (
            <Card className="gap-1 p-4">
              <span className="flex items-center gap-2 font-medium">
                <ChannelIcon channel={row.channel} />
                <span className="tabular">{show(row)}</span>
              </span>
              <span className="text-sm text-muted-foreground">
                {reason(row)} · <RelativeTime iso={row.created_at} />
              </span>
            </Card>
          )}
          footer={
            result.data && (
              <Pagination
                page={page}
                perPage={PER_PAGE}
                total={result.data.total}
                onPage={(next) =>
                  setParams(
                    (prev) => {
                      const p = new URLSearchParams(prev);
                      p.set('page', String(next));
                      return p;
                    },
                    {replace: true},
                  )
                }
              />
            )
          }
        />
      </div>
    </>
  );
}
