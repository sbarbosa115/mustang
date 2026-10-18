import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {
  CheckCircle2,
  FileSpreadsheet,
  TriangleAlert,
  Upload,
} from 'lucide-react';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  fetchVehicleImport,
  uploadVehiclesCsv,
  vehiclesKeys,
} from '@/entities/vehicle';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {Skeleton} from '@/shared/ui/skeleton';

const POLL_MS = 1500;
const SAMPLE_URL = '/samples/vehicles.csv';

/**
 * "Upload CSV": pick a file, it is queued, and the dialog follows the worker until it says done or failed, then
 * lists the rows it could not take.
 */
export function UploadVehiclesDialog() {
  const {t} = useTranslation('inventory');
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [importId, setImportId] = useState<string | null>(null);

  const upload = useMutation({
    mutationFn: (chosen: File) => uploadVehiclesCsv(chosen),
    onSuccess: (job) => setImportId(job.id),
  });
  const job = useQuery({
    queryKey: ['vehicles', 'import', importId],
    queryFn: () => fetchVehicleImport(importId as string),
    enabled: importId !== null,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'done' || status === 'failed' ? false : POLL_MS;
    },
  });
  const status = job.data?.status;
  const finished = status === 'done' || status === 'failed';

  const reset = () => {
    setFile(null);
    setImportId(null);
    upload.reset();
  };
  // The worker has added vehicles: the list behind this dialog must show them.
  useEffect(() => {
    if (finished) {
      void queryClient.invalidateQueries({queryKey: vehiclesKeys.all});
    }
  }, [finished, queryClient]);

  const errorText =
    upload.error instanceof ApiError
      ? t(`upload.error.${upload.error.code}`, {
          defaultValue: t('upload.error.generic'),
        })
      : upload.error
        ? t('upload.error.generic')
        : null;

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) reset();
      }}
    >
      <DialogTrigger asChild>
        <Button variant="outline">
          <Upload aria-hidden />
          {t('upload.button')}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90dvh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('upload.title')}</DialogTitle>
          <DialogDescription>{t('upload.description')}</DialogDescription>
        </DialogHeader>

        {importId === null ? (
          <form
            className="space-y-4"
            onSubmit={(event) => {
              event.preventDefault();
              if (file) upload.mutate(file);
            }}
          >
            <div className="space-y-1.5">
              <Label htmlFor="vehicles-csv">{t('upload.file')}</Label>
              <Input
                id="vehicles-csv"
                type="file"
                accept=".csv,text/csv"
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
              />
              <p className="text-sm text-muted-foreground">
                {t('upload.hint')}{' '}
                <a
                  href={SAMPLE_URL}
                  download
                  className="font-medium text-primary underline underline-offset-2"
                >
                  {t('upload.sample')}
                </a>
              </p>
            </div>
            {errorText && (
              <p role="alert" className="text-sm text-danger">
                {errorText}
              </p>
            )}
            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setOpen(false)}
              >
                {t('form.cancel')}
              </Button>
              <Button type="submit" disabled={!file || upload.isPending}>
                {t('upload.submit')}
              </Button>
            </div>
          </form>
        ) : (
          <div className="space-y-4" aria-live="polite">
            {!finished && (
              <div role="status" className="space-y-2">
                <Skeleton className="h-2 w-full" />
                <p className="text-sm text-muted-foreground">
                  {t('upload.processing')}
                </p>
              </div>
            )}
            {job.isError && (
              <p role="alert" className="text-sm text-danger">
                {t('upload.error.status')}
              </p>
            )}
            {job.data && finished && (
              <>
                <p
                  className="flex items-center gap-2 font-medium"
                  role="status"
                >
                  {status === 'done' ? (
                    <CheckCircle2 aria-hidden className="size-5 text-success" />
                  ) : (
                    <TriangleAlert aria-hidden className="size-5 text-danger" />
                  )}
                  {status === 'done'
                    ? t('upload.result', {
                        ok: job.data.rows_ok,
                        failed: job.data.rows_failed,
                        total: job.data.rows_total,
                      })
                    : t('upload.failed')}
                </p>
                {job.data.errors.length > 0 && (
                  <div className="space-y-2">
                    <h3 className="flex items-center gap-2 text-sm font-medium">
                      <FileSpreadsheet aria-hidden className="size-4" />
                      {t('upload.errors_title', {name: job.data.file_name})}
                    </h3>
                    <ul className="max-h-56 space-y-1 overflow-y-auto rounded-md border p-3 text-sm">
                      {job.data.errors.map((error, i) => (
                        <li key={`${error.row}-${i}`}>
                          <span className="tabular font-medium">
                            {t('upload.row', {row: error.row})}
                          </span>{' '}
                          {error.message}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={reset}>
                    {t('upload.another')}
                  </Button>
                  <Button onClick={() => setOpen(false)}>
                    {t('upload.done')}
                  </Button>
                </div>
              </>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
