import {useMutation, useQueryClient} from '@tanstack/react-query';
import {Download, Upload} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {importsKeys, startImport} from '@/entities/import-job';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {RadioGroup, RadioGroupItem} from '@/shared/ui/radio-group';

type ImportType = 'customers' | 'leads';
const TYPES: ImportType[] = ['customers', 'leads'];
const KNOWN_ERRORS = [
  'file_too_large',
  'file_not_csv',
  'file_required',
  'invalid_import_type',
  'forbidden',
];

/**
 * Choose customers or leads, pick a CSV and upload it: the worker imports it and the status list follows it.
 */
export function UploadImportForm() {
  const {t} = useTranslation('imports');
  const queryClient = useQueryClient();
  const [type, setType] = useState<ImportType>('customers');
  const [file, setFile] = useState<File | null>(null);
  // Bumped after an upload so the file input empties.
  const [inputKey, setInputKey] = useState(0);

  const upload = useMutation({
    mutationFn: ({chosen, kind}: {chosen: File; kind: ImportType}) =>
      startImport(kind, chosen),
    onSuccess: (job) => {
      toast.success(t('upload.queued', {name: job.file_name}));
      setFile(null);
      setInputKey((k) => k + 1);
      void queryClient.invalidateQueries({queryKey: importsKeys.all});
    },
  });

  const code =
    upload.error instanceof ApiError && KNOWN_ERRORS.includes(upload.error.code)
      ? upload.error.code
      : 'generic';

  return (
    <form
      className="space-y-5"
      onSubmit={(event) => {
        event.preventDefault();
        if (file) upload.mutate({chosen: file, kind: type});
      }}
    >
      <fieldset className="space-y-3">
        <legend className="text-sm font-medium">
          {t('upload.type_label')}
        </legend>
        <RadioGroup
          value={type}
          onValueChange={(value) => setType(value as ImportType)}
          className="flex flex-wrap gap-4"
        >
          {TYPES.map((value) => (
            <div key={value} className="flex min-h-11 items-center gap-2">
              <RadioGroupItem value={value} id={`import-type-${value}`} />
              <Label htmlFor={`import-type-${value}`}>
                {t(`upload.type.${value}`)}
              </Label>
            </div>
          ))}
        </RadioGroup>
        <p className="text-sm text-muted-foreground">
          {t(`upload.type_help.${type}`)}
        </p>
        <a
          href={`/api/v1/imports/template/${type}.csv`}
          download
          className="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary underline underline-offset-2 sm:min-h-0"
        >
          <Download aria-hidden className="size-4" />
          {t(`upload.sample.${type}`)}
        </a>
      </fieldset>

      <div className="space-y-1.5">
        <Label htmlFor="import-file">{t('upload.file')}</Label>
        <Input
          key={inputKey}
          id="import-file"
          type="file"
          accept=".csv,text/csv"
          onChange={(event) => {
            setFile(event.target.files?.[0] ?? null);
            upload.reset();
          }}
        />
        <p className="text-sm text-muted-foreground">{t('upload.hint')}</p>
      </div>

      {upload.isError && (
        <p role="alert" className="text-sm text-danger">
          {t(`upload.error.${code}`)}
        </p>
      )}

      <Button type="submit" disabled={!file || upload.isPending}>
        <Upload aria-hidden />
        {upload.isPending ? t('upload.submitting') : t('upload.submit')}
      </Button>
    </form>
  );
}
