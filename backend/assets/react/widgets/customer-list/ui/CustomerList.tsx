import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Search, Users} from 'lucide-react';
import {useEffect, useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useSearchParams} from 'react-router';
import {
  customersKeys,
  listCustomers,
  type CustomerPage,
} from '@/entities/customer';
import {useAuth} from '@/entities/user';
import {formatPhone} from '@/shared/lib/phone';
import {Badge} from '@/shared/ui/badge';
import {Input} from '@/shared/ui/input';
import {
  DataTable,
  Pagination,
  RelativeTime,
  StageBadge,
  type DealStage,
} from '@/shared/ui/mustang';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';

type Row = CustomerPage['items'][number];
type Scope = 'all' | 'mine' | 'archived';
const PER_PAGE = 25;
const SCOPES: Scope[] = ['all', 'mine', 'archived'];

interface Props {
  /** The empty state's one action: opening "Add customer". */
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

/** Customers of the dealership with search, a scope (all, mine, archived) and paging; filters live in the URL. */
export function CustomerList({emptyAction}: Props) {
  const {t} = useTranslation('customers');
  const {me} = useAuth();
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const urlQuery = params.get('q') ?? '';
  const scope = (SCOPES as string[]).includes(params.get('scope') ?? '')
    ? (params.get('scope') as Scope)
    : 'all';
  const page = Math.max(1, Number(params.get('page')) || 1);
  const [text, setText] = useState(urlQuery);
  const debounced = useDebounced(text.trim(), 300);

  // Keep the URL (and so the query) in step with what was typed, once typing pauses.
  useEffect(() => {
    if (debounced === urlQuery) return;
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
  }, [debounced, urlQuery, setParams]);

  const query = {
    page,
    per_page: PER_PAGE,
    ...(urlQuery ? {q: urlQuery} : {}),
    ...(scope === 'mine' && me ? {assigned_to: me.user.id} : {}),
    ...(scope === 'archived' ? {archived: true} : {}),
  };
  const result = useQuery({
    queryKey: customersKeys.list(query),
    queryFn: () => listCustomers(query),
    placeholderData: (previous) => previous,
  });

  const filtered = urlQuery !== '' || scope !== 'all';
  const clear = () => {
    setText('');
    setParams({}, {replace: true});
  };
  const setScope = (next: Scope) =>
    setParams(
      (prev) => {
        const p = new URLSearchParams(prev);
        if (next === 'all') p.delete('scope');
        else p.set('scope', next);
        p.delete('page');
        return p;
      },
      {replace: true},
    );

  const columns: Array<ColumnDef<Row, unknown>> = [
    {
      id: 'name',
      header: t('list.column.name'),
      cell: ({row}) => (
        <span className="flex items-center gap-2">
          <Link
            to={`/customers/${row.original.id}`}
            className="font-medium hover:underline"
            onClick={(event) => event.stopPropagation()}
          >
            {row.original.name}
          </Link>
          {row.original.archived && (
            <Badge variant="secondary">{t('list.archived')}</Badge>
          )}
        </span>
      ),
    },
    {
      id: 'phone',
      header: t('list.column.phone'),
      cell: ({row}) => (
        <span className="tabular">
          {formatPhone(row.original.primary_phone)}
        </span>
      ),
    },
    {
      id: 'email',
      header: t('list.column.email'),
      cell: ({row}) => row.original.primary_email ?? '',
    },
    {
      id: 'sales1',
      header: t('list.column.sales1'),
      cell: ({row}) =>
        row.original.sales1?.name ?? (
          <span className="text-muted-foreground">{t('list.unassigned')}</span>
        ),
    },
    {
      id: 'stage',
      header: t('list.column.stage'),
      cell: ({row}) =>
        row.original.open_deal_stage ? (
          <StageBadge stage={row.original.open_deal_stage as DealStage} />
        ) : (
          <span className="text-muted-foreground">{t('list.no_deal')}</span>
        ),
    },
    {
      id: 'updated',
      header: t('list.column.updated'),
      cell: ({row}) => (
        <RelativeTime
          iso={row.original.updated_at}
          className="text-muted-foreground"
        />
      ),
    },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Search
            aria-hidden
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
          />
          <label htmlFor="customer_search" className="sr-only">
            {t('list.search_label')}
          </label>
          <Input
            id="customer_search"
            type="search"
            className="pl-9"
            value={text}
            placeholder={t('list.search_placeholder')}
            onChange={(event) => setText(event.target.value)}
          />
        </div>
        <Select
          value={scope}
          onValueChange={(value) => setScope(value as Scope)}
        >
          <SelectTrigger
            aria-label={t('list.scope_label')}
            className="w-full sm:w-52"
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
      <DataTable
        columns={columns}
        data={result.data?.items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        filtered={filtered}
        onClearFilters={clear}
        empty={{
          icon: Users,
          title:
            scope === 'archived' ? t('list.empty_archived') : t('list.empty'),
          action: scope === 'archived' ? undefined : emptyAction,
        }}
        getRowId={(row) => row.id}
        onRowClick={(row) => void navigate(`/customers/${row.id}`)}
        renderCard={(row) => (
          <Link
            to={`/customers/${row.id}`}
            className="block rounded-lg border bg-card p-3 text-card-foreground shadow-xs"
          >
            <span className="flex items-center justify-between gap-2">
              <span className="font-medium">{row.name}</span>
              {row.open_deal_stage && (
                <StageBadge stage={row.open_deal_stage as DealStage} />
              )}
            </span>
            {row.primary_phone && (
              <span className="tabular block text-sm text-muted-foreground">
                {formatPhone(row.primary_phone)}
              </span>
            )}
            {row.primary_email && (
              <span className="block truncate text-sm text-muted-foreground">
                {row.primary_email}
              </span>
            )}
            <span className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
              <span>{row.sales1?.name ?? t('list.unassigned')}</span>
              {row.archived && (
                <Badge variant="secondary">{t('list.archived')}</Badge>
              )}
            </span>
          </Link>
        )}
        footer={
          result.data && (
            <Pagination
              page={page}
              perPage={PER_PAGE}
              total={result.data.total}
              onPage={(next) =>
                setParams((prev) => {
                  const p = new URLSearchParams(prev);
                  p.set('page', String(next));
                  return p;
                })
              }
            />
          )
        }
      />
    </div>
  );
}
