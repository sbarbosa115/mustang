import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {FileSpreadsheet} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {importsKeys, listImports, type ImportJob} from '@/entities/import-job';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {DataTable, Pagination, RelativeTime} from '@/shared/ui/mustang';

const PER_PAGE = 10;
const POLL_MS = 2000;
const VARIANT = {
  pending: 'secondary',
  processing: 'secondary',
  done: 'outline',
  failed: 'destructive',
} as const;

const isRunning = (job: ImportJob) =>
  job.status === 'pending' || job.status === 'processing';

function StatusBadge({job}: {job: ImportJob}) {
  const {t} = useTranslation('imports');
  return (
    <Badge variant={VARIANT[job.status]}>
      {t(`status.state.${job.status}`)}
    </Badge>
  );
}

function Result({job}: {job: ImportJob}) {
  const {t} = useTranslation('imports');
  const [open, setOpen] = useState(false);
  if (isRunning(job)) {
    return <span className="text-muted-foreground">{t('status.waiting')}</span>;
  }
  const listId = `import-errors-${job.id}`;
  return (
    <div className="space-y-1">
      <p>
        {job.status === 'failed'
          ? t('status.result_failed')
          : t('status.result', {
              created: job.rows_created,
              updated: job.rows_updated,
              skipped: job.rows_skipped,
              failed: job.rows_failed,
            })}
      </p>
      {job.errors.length > 0 && (
        <>
          <Button
            variant="link"
            size="sm"
            className="h-auto min-h-11 px-0 sm:min-h-0"
            aria-expanded={open}
            aria-controls={listId}
            onClick={() => setOpen((v) => !v)}
          >
            {open
              ? t('status.hide_errors')
              : t('status.show_errors', {count: job.errors.length})}
          </Button>
          {open && (
            <ul
              id={listId}
              className="max-h-56 space-y-1 overflow-y-auto rounded-md border p-3 text-sm whitespace-normal"
            >
              {job.errors.map((error, i) => (
                <li key={`${error.row}-${i}`}>
                  <span className="tabular font-medium">
                    {t('status.row', {row: error.row})}
                  </span>{' '}
                  {error.message}
                  {error.field && (
                    <span className="text-muted-foreground">
                      {' '}
                      ({t('status.column_hint', {field: error.field})})
                    </span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </>
      )}
    </div>
  );
}

/** The dealership's imports, newest first; it polls while one is queued or importing. */
export function ImportStatus() {
  const {t} = useTranslation('imports');
  const [page, setPage] = useState(1);
  const query = {page, per_page: PER_PAGE};
  const result = useQuery({
    queryKey: importsKeys.list(query),
    queryFn: () => listImports(query),
    placeholderData: (previous) => previous,
    refetchInterval: (q) =>
      q.state.data?.items.some(isRunning) ? POLL_MS : false,
  });

  const columns: Array<ColumnDef<ImportJob, unknown>> = [
    {
      id: 'file',
      header: t('status.column.file'),
      cell: ({row}) => (
        <div>
          <p className="font-medium break-all">{row.original.file_name}</p>
          {row.original.uploaded_by && (
            <p className="text-sm text-muted-foreground">
              {t('status.by', {name: row.original.uploaded_by.name})}
            </p>
          )}
        </div>
      ),
    },
    {
      id: 'type',
      header: t('status.column.type'),
      cell: ({row}) => t(`upload.type.${row.original.type}`),
    },
    {
      id: 'status',
      header: t('status.column.status'),
      cell: ({row}) => <StatusBadge job={row.original} />,
    },
    {
      id: 'result',
      header: t('status.column.result'),
      cell: ({row}) => <Result job={row.original} />,
    },
    {
      id: 'uploaded',
      header: t('status.column.uploaded'),
      cell: ({row}) => (
        <RelativeTime
          iso={row.original.created_at}
          className="text-muted-foreground"
        />
      ),
    },
  ];

  return (
    <section aria-labelledby="import-status-title" className="space-y-3">
      <h2 id="import-status-title" className="text-lg font-semibold">
        {t('status.title')}
      </h2>
      <DataTable
        columns={columns}
        data={result.data?.items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        getRowId={(job) => job.id}
        empty={{icon: FileSpreadsheet, title: t('status.empty')}}
        renderCard={(job) => (
          <Card className="gap-2 p-4">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="font-medium break-all">{job.file_name}</p>
                <p className="text-sm text-muted-foreground">
                  {t(`upload.type.${job.type}`)} ·{' '}
                  <RelativeTime iso={job.created_at} />
                </p>
              </div>
              <StatusBadge job={job} />
            </div>
            <Result job={job} />
          </Card>
        )}
        footer={
          result.data && (
            <Pagination
              page={page}
              perPage={PER_PAGE}
              total={result.data.total}
              onPage={setPage}
            />
          )
        }
      />
    </section>
  );
}
