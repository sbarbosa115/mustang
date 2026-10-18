import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {
  Eye,
  History,
  Pencil,
  Plus,
  ScrollText,
  Trash2,
  type LucideIcon,
} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link, useSearchParams} from 'react-router';
import {auditKeys, listAuditEntries} from '@/entities/audit-entry';
import {useAuth} from '@/entities/user';
import {
  AuditFilters,
  EMPTY_AUDIT_FILTERS,
  entityLabel,
  filtersFromParams,
  filtersToParams,
  isAuditFiltered,
  toAuditQuery,
} from '@/features/filter-audit';
import type {Schema} from '@/shared/api';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {DataTable, Pagination, RelativeTime} from '@/shared/ui/mustang';
import {AuditChanges} from './AuditChanges';

type Row = Schema<'AuditEntryOutput'>;
const PER_PAGE = 25;

const ACTION_ICON: Record<Row['action'], LucideIcon> = {
  create: Plus,
  update: Pencil,
  delete: Trash2,
  read: Eye,
};

function HeaderLabel({k}: {k: string}) {
  const {t} = useTranslation('audit');
  return t(k);
}

function When({iso}: {iso: string}) {
  const {me} = useAuth();
  return (
    <RelativeTime
      iso={iso}
      timeZone={me?.dealership.timezone}
      className="whitespace-nowrap"
    />
  );
}

function Who({row}: {row: Row}) {
  const {t} = useTranslation('audit');
  const name = row.actor
    ? row.actor.name || t('list.former_user')
    : t('list.system');
  return (
    <span className="flex flex-col">
      <span className={row.actor ? 'font-medium' : 'text-muted-foreground'}>
        {name}
      </span>
      {row.ip && (
        <span className="text-xs text-muted-foreground">
          {t('list.ip', {ip: row.ip})}
        </span>
      )}
    </span>
  );
}

function ActionBadge({action}: {action: Row['action']}) {
  const {t} = useTranslation('audit');
  const Icon = ACTION_ICON[action];
  return (
    <Badge variant="outline">
      <Icon aria-hidden /> {t(`action.${action}`)}
    </Badge>
  );
}

function RecordCell({row}: {row: Row}) {
  const {t} = useTranslation('audit');
  const [params, setParams] = useSearchParams();
  const showHistory = () =>
    setParams(
      filtersToParams(
        {
          ...filtersFromParams(params),
          entityType: row.entity_type,
          entityId: row.entity_id,
        },
        params,
      ),
    );
  return (
    <span className="flex items-center gap-1">
      <span className="flex min-w-0 flex-col">
        <span>{entityLabel(t, row.entity_type)}</span>
        {row.customer_id ? (
          <Link
            to={`/customers/${row.customer_id}`}
            className="text-xs text-primary underline-offset-4 hover:underline"
          >
            {t('list.open_customer')}
          </Link>
        ) : (
          <span className="font-mono text-xs text-muted-foreground">
            {row.entity_id.slice(0, 8)}
          </span>
        )}
      </span>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        aria-label={t('list.history')}
        title={t('list.history')}
        onClick={showHistory}
      >
        <History aria-hidden />
      </Button>
    </span>
  );
}

/** Module-level on purpose: flexRender treats each `cell` as a component, so stable columns keep their state. */
const columns: Array<ColumnDef<Row, unknown>> = [
  {
    id: 'when',
    header: () => <HeaderLabel k="list.column.when" />,
    cell: ({row}) => <When iso={row.original.occurred_at} />,
  },
  {
    id: 'who',
    header: () => <HeaderLabel k="list.column.who" />,
    cell: ({row}) => <Who row={row.original} />,
  },
  {
    id: 'action',
    header: () => <HeaderLabel k="list.column.action" />,
    cell: ({row}) => <ActionBadge action={row.original.action} />,
  },
  {
    id: 'record',
    header: () => <HeaderLabel k="list.column.record" />,
    cell: ({row}) => <RecordCell row={row.original} />,
  },
  {
    id: 'changes',
    header: () => <HeaderLabel k="list.column.changes" />,
    cell: ({row}) => (
      <AuditChanges
        action={row.original.action}
        changes={row.original.changes}
      />
    ),
  },
];

/** The dealership's audit log, newest first, with its filters in the URL (admin). */
export function AuditTable() {
  const {t} = useTranslation('audit');
  const [params, setParams] = useSearchParams();
  const filters = filtersFromParams(params);
  const page = Math.max(1, Number(params.get('page')) || 1);
  const query = toAuditQuery(filters, page, PER_PAGE);
  const result = useQuery({
    queryKey: auditKeys.list(query),
    queryFn: () => listAuditEntries(query),
    placeholderData: (previous) => previous,
  });

  return (
    <div className="space-y-4">
      <AuditFilters
        value={filters}
        onChange={(next) => setParams(filtersToParams(next, params))}
      />
      <DataTable
        columns={columns}
        data={result.data?.items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        filtered={isAuditFiltered(filters)}
        onClearFilters={() =>
          setParams(filtersToParams(EMPTY_AUDIT_FILTERS, params))
        }
        empty={{icon: ScrollText, title: t('list.empty')}}
        getRowId={(row) => row.id}
        renderCard={(row) => (
          <div className="space-y-2 rounded-lg border bg-card p-3 text-card-foreground shadow-xs">
            <div className="flex items-start justify-between gap-2">
              <Who row={row} />
              <span className="text-sm text-muted-foreground">
                <When iso={row.occurred_at} />
              </span>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <ActionBadge action={row.action} />
              <RecordCell row={row} />
            </div>
            <AuditChanges action={row.action} changes={row.changes} />
          </div>
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
