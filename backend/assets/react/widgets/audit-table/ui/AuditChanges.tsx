import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {Button} from '@/shared/ui/button';

const SHOWN = 3;
/** What the server writes in place of a password hash, an MFA secret or recovery codes. */
export const MASKED = '[masked]';

type Changes = NonNullable<Schema<'AuditEntryOutput'>['changes']>;

interface Diff {
  field: string;
  old: unknown;
  new: unknown;
}

function diffs(changes: Changes): Diff[] {
  return Object.entries(changes).map(([field, value]) => {
    const pair = (value ?? {}) as {old?: unknown; new?: unknown};
    return {field, old: pair.old ?? null, new: pair.new ?? null};
  });
}

function Value({value}: {value: unknown}) {
  const {t} = useTranslation('audit');
  if (value === null || value === '') {
    return <span className="text-muted-foreground">{t('changes.empty')}</span>;
  }
  if (value === MASKED) {
    return (
      <span className="text-muted-foreground italic">
        {t('changes.masked')}
      </span>
    );
  }
  const text =
    typeof value === 'object' ? JSON.stringify(value) : String(value);
  return <span className="break-all">{text}</span>;
}

interface Props {
  action: Schema<'AuditEntryOutput'>['action'];
  changes: Schema<'AuditEntryOutput'>['changes'];
}

/** The fields of one entry, old → new (a creation shows the new values, a deletion the removed ones). */
export function AuditChanges({action, changes}: Props) {
  const {t} = useTranslation('audit');
  const [all, setAll] = useState(false);
  if (action === 'read') return null;
  const list = changes ? diffs(changes) : [];
  if (list.length === 0) {
    return <span className="text-muted-foreground">{t('changes.none')}</span>;
  }
  const shown = all ? list : list.slice(0, SHOWN);
  return (
    <div className="space-y-1">
      <dl className="space-y-0.5 text-sm">
        {shown.map((diff) => (
          <div key={diff.field} className="flex flex-wrap gap-x-1.5">
            <dt className="font-mono text-xs leading-5 text-muted-foreground">
              {diff.field}
            </dt>
            <dd className="flex flex-wrap gap-x-1">
              {action === 'update' && (
                <>
                  <Value value={diff.old} />
                  <span aria-hidden>→</span>
                </>
              )}
              <Value value={action === 'delete' ? diff.old : diff.new} />
            </dd>
          </div>
        ))}
      </dl>
      {list.length > SHOWN && (
        <Button
          type="button"
          variant="link"
          size="sm"
          className="h-auto p-0"
          onClick={() => setAll((current) => !current)}
        >
          {all
            ? t('changes.show_less')
            : t('changes.show_more', {count: list.length - SHOWN})}
        </Button>
      )}
    </div>
  );
}
