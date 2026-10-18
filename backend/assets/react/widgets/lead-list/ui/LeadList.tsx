import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Inbox, Search} from 'lucide-react';
import {useEffect, useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useSearchParams} from 'react-router';
import {leadsKeys, listLeads, type LeadPage} from '@/entities/lead';
import {useAuth} from '@/entities/user';
import type {Schema} from '@/shared/api';
import {Input} from '@/shared/ui/input';
import {DataTable, Pagination, RelativeTime} from '@/shared/ui/mustang';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {LeadStatusBadge} from './LeadStatusBadge';

type Row = LeadPage['items'][number];
type Status = Schema<'LeadStatus'>;
type Source = Schema<'LeadSource'>;
type Scope = 'all' | 'mine';
const PER_PAGE = 25;
const ALL = 'all';
const STATUSES: Status[] = ['new', 'assigned', 'contacted', 'closed'];
const SOURCES: Source[] = [
  'website',
  'phone',
  'walk_in',
  'manual',
  'import',
  'other',
];
const SCOPES: Scope[] = ['all', 'mine'];

interface Props {
  /** The empty state's one action: opening "Capture lead". */
  emptyAction?: ReactNode;
}

function useDebounced<T>(value: T, ms: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), ms);
    return () => clearTimeout(timer);
  }, [value, ms]);
  return debounced;
}

function pick<T extends string>(value: string | null, allowed: T[]): T | null {
  return (allowed as string[]).includes(value ?? '') ? (value as T) : null;
}

/** The dealership's leads, newest first, with search, status, source and "my leads"; filters live in the URL. */
export function LeadList({emptyAction}: Props) {
  const {t} = useTranslation('leads');
  const {me} = useAuth();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const urlQuery = params.get('q') ?? '';
  const status = pick(params.get('status'), STATUSES);
  const source = pick(params.get('source'), SOURCES);
  const scope = pick(params.get('scope'), SCOPES) ?? 'all';
  const page = Math.max(1, Number(params.get('page')) || 1);
  const [text, setText] = useState(urlQuery);
  const debounced = useDebounced(text.trim(), 300);

  const setParam = (key: string, value: string | null) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (value === null) next.delete(key);
        else next.set(key, value);
        if (key !== 'page') next.delete('page');
        return next;
      },
      {replace: key !== 'page'},
    );

  // Keep the URL (and so the query) in step with what was typed, once typing pauses.
  useEffect(() => {
    // Wait until typing has settled, so clearing the box is not undone by a stale debounced value.
    if (debounced === urlQuery || debounced !== text.trim()) return;
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (debounced) next.set('q', debounced);
        else next.delete('q');
        next.delete('page');
        return next;
      },
      {replace: true},
    );
  }, [debounced, text, urlQuery, setParams]);

  const query = {
    page,
    per_page: PER_PAGE,
    ...(urlQuery ? {q: urlQuery} : {}),
    ...(status ? {status} : {}),
    ...(source ? {source} : {}),
    ...(scope === 'mine' && me ? {assigned_to: me.user.id} : {}),
  };
  const result = useQuery({
    queryKey: leadsKeys.list(query),
    queryFn: () => listLeads(query),
    placeholderData: (previous) => previous,
  });

  const filtered =
    urlQuery !== '' || status !== null || source !== null || scope !== 'all';
  const clear = () => {
    setText('');
    setParams({}, {replace: true});
  };

  const columns: Array<ColumnDef<Row, unknown>> = [
    {
      id: 'customer',
      header: t('list.column.customer'),
      cell: ({row}) => (
        <Link
          to={`/leads/${row.original.id}`}
          className="font-medium hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.original.customer.name}
        </Link>
      ),
    },
    {
      id: 'source',
      header: t('list.column.source'),
      cell: ({row}) => t(`source.${row.original.source}`),
    },
    {
      id: 'vehicle',
      header: t('list.column.vehicle'),
      cell: ({row}) =>
        row.original.vehicle_interest_text ?? (
          <span className="text-muted-foreground">{t('list.no_vehicle')}</span>
        ),
    },
    {
      id: 'assignee',
      header: t('list.column.assignee'),
      cell: ({row}) =>
        row.original.assignee?.name ?? (
          <span className="text-muted-foreground">{t('list.unassigned')}</span>
        ),
    },
    {
      id: 'status',
      header: t('list.column.status'),
      cell: ({row}) => <LeadStatusBadge status={row.original.status} />,
    },
    {
      id: 'received',
      header: t('list.column.received'),
      cell: ({row}) => (
        <RelativeTime
          iso={row.original.received_at}
          className="text-muted-foreground"
        />
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-2 lg:flex-row">
        <div className="relative flex-1">
          <Search
            aria-hidden
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
          />
          <label htmlFor="lead_search" className="sr-only">
            {t('list.search_label')}
          </label>
          <Input
            id="lead_search"
            type="search"
            className="pl-9"
            value={text}
            placeholder={t('list.search_placeholder')}
            onChange={(event) => setText(event.target.value)}
          />
        </div>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:flex">
          <Select
            value={status ?? ALL}
            onValueChange={(value) =>
              setParam('status', value === ALL ? null : value)
            }
          >
            <SelectTrigger
              aria-label={t('list.status_label')}
              className="w-full lg:w-44"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>{t('list.all_statuses')}</SelectItem>
              {STATUSES.map((s) => (
                <SelectItem key={s} value={s}>
                  {t(`status.${s}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={source ?? ALL}
            onValueChange={(value) =>
              setParam('source', value === ALL ? null : value)
            }
          >
            <SelectTrigger
              aria-label={t('list.source_label')}
              className="w-full lg:w-44"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>{t('list.all_sources')}</SelectItem>
              {SOURCES.map((s) => (
                <SelectItem key={s} value={s}>
                  {t(`source.${s}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={scope}
            onValueChange={(value) =>
              setParam('scope', value === 'all' ? null : value)
            }
          >
            <SelectTrigger
              aria-label={t('list.scope_label')}
              className="w-full lg:w-40"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {SCOPES.map((s) => (
                <SelectItem key={s} value={s}>
                  {t(`list.scope.${s}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
      <DataTable
        columns={columns}
        data={result.data?.items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        filtered={filtered}
        onClearFilters={clear}
        empty={{icon: Inbox, title: t('list.empty'), action: emptyAction}}
        getRowId={(row) => row.id}
        onRowClick={(row) => void navigate(`/leads/${row.id}`)}
        renderCard={(row) => (
          <Link
            to={`/leads/${row.id}`}
            className="block rounded-lg border bg-card p-3 text-card-foreground shadow-xs"
          >
            <span className="flex items-center justify-between gap-2">
              <span className="font-medium">{row.customer.name}</span>
              <LeadStatusBadge status={row.status} />
            </span>
            {row.vehicle_interest_text && (
              <span className="block truncate text-sm text-muted-foreground">
                {row.vehicle_interest_text}
              </span>
            )}
            <span className="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
              <span>{t(`source.${row.source}`)}</span>
              <span aria-hidden>·</span>
              <span>{row.assignee?.name ?? t('list.unassigned')}</span>
              <span aria-hidden>·</span>
              <RelativeTime iso={row.received_at} />
            </span>
          </Link>
        )}
        footer={
          result.data && (
            <Pagination
              page={page}
              perPage={PER_PAGE}
              total={result.data.total}
              onPage={(next) => setParam('page', String(next))}
            />
          )
        }
      />
    </div>
  );
}
