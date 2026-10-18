import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Search, Users} from 'lucide-react';
import {useEffect, useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {listUsers, useAuth, usersKeys} from '@/entities/user';
import type {Schema} from '@/shared/api';
import {Badge} from '@/shared/ui/badge';
import {Input} from '@/shared/ui/input';
import {DataTable, Pagination, RelativeTime} from '@/shared/ui/mustang';
import {UserRowActions} from './UserRowActions';

type Row = Schema<'UserOutput'>;
const PER_PAGE = 25;

interface Props {
  /** The empty state's one action: opening "Add user". */
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

function StatusBadge({user}: {user: Row}) {
  const {t} = useTranslation('users');
  return (
    <Badge variant={user.active ? 'outline' : 'secondary'}>
      {user.active ? t('list.status.active') : t('list.status.inactive')}
    </Badge>
  );
}

function HeaderLabel({k}: {k: string}) {
  const {t} = useTranslation('users');
  return t(k);
}

function YouBadge({id}: {id: string}) {
  const {t} = useTranslation('users');
  const {me} = useAuth();
  return id === me?.user.id ? (
    <Badge variant="secondary">{t('list.you')}</Badge>
  ) : null;
}

function RoleCell({role}: {role: Row['role']}) {
  const {t} = useTranslation('users');
  return t(`settings:role.${role}`);
}

function MfaCell({user}: {user: Row}) {
  const {t} = useTranslation('users');
  return user.mfa_enrolled ? t('list.mfa.on') : t('list.mfa.off');
}

function LastLoginCell({iso}: {iso: string | null}) {
  const {t} = useTranslation('users');
  return iso ? (
    <RelativeTime iso={iso} className="text-muted-foreground" />
  ) : (
    <span className="text-muted-foreground">{t('list.never_signed_in')}</span>
  );
}

/**
 * Module-level on purpose: TanStack's flexRender treats each `cell` function as a component, so columns rebuilt on
 * every render would remount the row menus and close their open dialogs. Translations happen inside the cells.
 */
const columns: Array<ColumnDef<Row, unknown>> = [
  {
    id: 'name',
    header: () => <HeaderLabel k="list.column.name" />,
    cell: ({row}) => (
      <span className="flex items-center gap-2">
        <span className="font-medium">{row.original.name}</span>
        <YouBadge id={row.original.id} />
      </span>
    ),
  },
  {
    id: 'email',
    header: () => <HeaderLabel k="list.column.email" />,
    cell: ({row}) => row.original.email,
  },
  {
    id: 'role',
    header: () => <HeaderLabel k="list.column.role" />,
    cell: ({row}) => <RoleCell role={row.original.role} />,
  },
  {
    id: 'status',
    header: () => <HeaderLabel k="list.column.status" />,
    cell: ({row}) => <StatusBadge user={row.original} />,
  },
  {
    id: 'mfa',
    header: () => <HeaderLabel k="list.column.mfa" />,
    cell: ({row}) => <MfaCell user={row.original} />,
  },
  {
    id: 'last_login',
    header: () => <HeaderLabel k="list.column.last_login" />,
    cell: ({row}) => <LastLoginCell iso={row.original.last_login_at} />,
  },
  {
    id: 'actions',
    header: () => (
      <span className="sr-only">
        <HeaderLabel k="list.actions_header" />
      </span>
    ),
    cell: ({row}) => (
      <div className="flex justify-end">
        <UserRowActions user={row.original} />
      </div>
    ),
  },
];

/** The dealership's users with search and paging; the search lives in the URL. Each row has its action menu. */
export function UserTable({emptyAction}: Props) {
  const {t} = useTranslation('users');
  const [params, setParams] = useSearchParams();
  const urlQuery = params.get('q') ?? '';
  const page = Math.max(1, Number(params.get('page')) || 1);
  const [text, setText] = useState(urlQuery);
  const debounced = useDebounced(text.trim(), 300);

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

  const query = {page, per_page: PER_PAGE, ...(urlQuery ? {q: urlQuery} : {})};
  const result = useQuery({
    queryKey: usersKeys.list(query),
    queryFn: () => listUsers(query),
    placeholderData: (previous) => previous,
  });
  const clear = () => {
    setText('');
    setParams({}, {replace: true});
  };

  return (
    <div className="space-y-4">
      <div className="relative">
        <Search
          aria-hidden
          className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <label htmlFor="user_search" className="sr-only">
          {t('list.search_label')}
        </label>
        <Input
          id="user_search"
          type="search"
          className="pl-9"
          value={text}
          placeholder={t('list.search_placeholder')}
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
        onClearFilters={clear}
        empty={{icon: Users, title: t('list.empty'), action: emptyAction}}
        getRowId={(row) => row.id}
        renderCard={(row) => (
          <div className="flex items-start justify-between gap-2 rounded-lg border bg-card p-3 text-card-foreground shadow-xs">
            <div className="min-w-0 space-y-1">
              <p className="flex items-center gap-2 font-medium">
                {row.name}
                <YouBadge id={row.id} />
              </p>
              <p className="truncate text-sm text-muted-foreground">
                {row.email}
              </p>
              <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                <span>{t(`settings:role.${row.role}`)}</span>
                <StatusBadge user={row} />
                <span>
                  {row.mfa_enrolled ? t('list.mfa.on') : t('list.mfa.off')}
                </span>
              </p>
            </div>
            <UserRowActions user={row} />
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
