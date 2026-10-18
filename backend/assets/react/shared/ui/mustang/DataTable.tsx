import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table';
import {AlertTriangle, SearchX, type LucideIcon} from 'lucide-react';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Button} from '../button';
import {Card} from '../card';
import {Skeleton} from '../skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../table';
import {EmptyState} from './EmptyState';

interface Props<T> {
  columns: Array<ColumnDef<T, unknown>>;
  data: T[] | undefined;
  isLoading: boolean;
  error?: unknown;
  onRetry?: () => void;
  /** True when filters or a search are applied, so an empty result offers "Show all". */
  filtered?: boolean;
  onClearFilters?: () => void;
  empty: {icon: LucideIcon; title: string; action?: ReactNode};
  /** How one row looks on a phone; the table is used from the sm breakpoint. */
  renderCard?: (row: T) => ReactNode;
  getRowId?: (row: T) => string;
  onRowClick?: (row: T) => void;
  footer?: ReactNode;
}

/**
 * Every list in Mustang: loading skeletons, an error with retry, an empty state, a "filtered to nothing" state that
 * clears every filter, a table that scrolls inside its card, and cards on phones (steps/04-build.md "UI/UX rules").
 */
export function DataTable<T>({
  columns,
  data,
  isLoading,
  error,
  onRetry,
  filtered = false,
  onClearFilters,
  empty,
  renderCard,
  getRowId,
  onRowClick,
  footer,
}: Props<T>) {
  const {t} = useTranslation('common');
  // TanStack Table returns functions React Compiler must not memoize; this component is the only caller.
  // eslint-disable-next-line react-hooks/incompatible-library -- useReactTable is designed to be called this way
  const table = useReactTable({
    data: data ?? [],
    columns,
    getCoreRowModel: getCoreRowModel(),
    getRowId,
  });

  if (isLoading) {
    return (
      <Card
        className="gap-2 p-4"
        aria-busy="true"
        aria-label={t('list.loading')}
      >
        {Array.from({length: 5}, (_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </Card>
    );
  }
  if (error) {
    return (
      <Card>
        <EmptyState
          icon={AlertTriangle}
          title={t('list.error')}
          action={
            onRetry && (
              <Button variant="outline" onClick={onRetry}>
                {t('action.retry')}
              </Button>
            )
          }
        />
      </Card>
    );
  }
  if (!data || data.length === 0) {
    return (
      <Card>
        {filtered ? (
          <EmptyState
            icon={SearchX}
            title={t('list.filtered_empty')}
            action={
              onClearFilters && (
                <Button variant="outline" onClick={onClearFilters}>
                  {t('list.show_all')}
                </Button>
              )
            }
          />
        ) : (
          <EmptyState
            icon={empty.icon}
            title={empty.title}
            action={empty.action}
          />
        )}
      </Card>
    );
  }

  return (
    <div className="space-y-3">
      {renderCard && (
        <ul className="space-y-2 sm:hidden">
          {data.map((row, i) => (
            <li key={getRowId ? getRowId(row) : i}>{renderCard(row)}</li>
          ))}
        </ul>
      )}
      <Card className={renderCard ? 'hidden gap-0 py-0 sm:flex' : 'gap-0 py-0'}>
        <div className="overflow-x-auto">
          <Table>
            <TableHeader className="bg-muted">
              {table.getHeaderGroups().map((group) => (
                <TableRow key={group.id}>
                  {group.headers.map((header) => (
                    <TableHead key={header.id}>
                      {header.isPlaceholder
                        ? null
                        : flexRender(
                            header.column.columnDef.header,
                            header.getContext(),
                          )}
                    </TableHead>
                  ))}
                </TableRow>
              ))}
            </TableHeader>
            <TableBody>
              {table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  onClick={
                    onRowClick ? () => onRowClick(row.original) : undefined
                  }
                  className={onRowClick ? 'cursor-pointer' : undefined}
                >
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(
                        cell.column.columnDef.cell,
                        cell.getContext(),
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </Card>
      {footer}
    </div>
  );
}
