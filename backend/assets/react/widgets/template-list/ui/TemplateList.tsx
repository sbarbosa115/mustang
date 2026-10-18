import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {FileText, Pencil, Trash2} from 'lucide-react';
import {useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {listTemplates, templatesKeys, type Template} from '@/entities/template';
import {DeleteTemplateDialog, TemplateDialog} from '@/features/manage-template';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {ChannelIcon, DataTable, RelativeTime} from '@/shared/ui/mustang';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';

const ALL = 'all';
const CHANNELS = ['text', 'email'] as const;

interface Props {
  /** Managers edit and delete; everyone else only reads. */
  canManage: boolean;
  /** The empty state's one action: opening "New template". */
  emptyAction?: ReactNode;
}

/** The dealership's templates by name, with a channel filter and (for managers) edit and delete. */
export function TemplateList({canManage, emptyAction}: Props) {
  const {t} = useTranslation('templates');
  const [channel, setChannel] = useState<(typeof CHANNELS)[number] | null>(
    null,
  );
  const [editing, setEditing] = useState<Template | null>(null);
  const [deleting, setDeleting] = useState<Template | null>(null);
  const query = channel ? {channel} : {};
  const result = useQuery({
    queryKey: templatesKeys.list(query),
    queryFn: () => listTemplates(query),
  });

  const actions = (row: Template) =>
    canManage && (
      <span className="flex gap-1">
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('list.edit', {name: row.name})}
          onClick={(event) => {
            event.stopPropagation();
            setEditing(row);
          }}
        >
          <Pencil />
        </Button>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('list.delete', {name: row.name})}
          onClick={(event) => {
            event.stopPropagation();
            setDeleting(row);
          }}
        >
          <Trash2 />
        </Button>
      </span>
    );

  const status = (row: Template) => (
    <Badge variant={row.active ? 'secondary' : 'outline'}>
      {row.active ? t('list.active') : t('list.inactive')}
    </Badge>
  );

  const channelLabel = (row: Template) => (
    <span className="inline-flex items-center gap-1.5">
      <ChannelIcon channel={row.channel as 'text' | 'email'} />
      {t(`channel.${row.channel as 'text' | 'email'}`)}
    </span>
  );

  const columns: Array<ColumnDef<Template, unknown>> = [
    {
      id: 'name',
      header: t('list.column.name'),
      cell: ({row}) => <span className="font-medium">{row.original.name}</span>,
    },
    {
      id: 'channel',
      header: t('list.column.channel'),
      cell: ({row}) => channelLabel(row.original),
    },
    {
      id: 'message',
      header: t('list.column.message'),
      cell: ({row}) => (
        <span className="line-clamp-1 max-w-md text-muted-foreground">
          {row.original.body}
        </span>
      ),
    },
    {
      id: 'status',
      header: t('list.column.status'),
      cell: ({row}) => status(row.original),
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
    ...(canManage
      ? [
          {
            id: 'actions',
            header: () => (
              <span className="sr-only">{t('list.column.actions')}</span>
            ),
            cell: ({row}: {row: {original: Template}}) => actions(row.original),
          } satisfies ColumnDef<Template, unknown>,
        ]
      : []),
  ];

  return (
    <div className="space-y-4">
      <Select
        value={channel ?? ALL}
        onValueChange={(value) =>
          setChannel(
            value === ALL ? null : (value as (typeof CHANNELS)[number]),
          )
        }
      >
        <SelectTrigger
          aria-label={t('list.column.channel')}
          className="w-full sm:w-44"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>{t('list.all_channels')}</SelectItem>
          {CHANNELS.map((c) => (
            <SelectItem key={c} value={c}>
              {t(`channel.${c}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <DataTable
        columns={columns}
        data={result.data?.items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        filtered={channel !== null}
        onClearFilters={() => setChannel(null)}
        empty={{icon: FileText, title: t('list.empty'), action: emptyAction}}
        getRowId={(row) => row.id}
        renderCard={(row) => (
          <div className="rounded-lg border bg-card p-3 text-card-foreground shadow-xs">
            <div className="flex items-center justify-between gap-2">
              <span className="font-medium">{row.name}</span>
              {status(row)}
            </div>
            <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
              {row.body}
            </p>
            <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
              {channelLabel(row)}
              {actions(row)}
            </div>
          </div>
        )}
      />
      {editing && (
        <TemplateDialog
          open
          template={editing}
          onOpenChange={(open) => !open && setEditing(null)}
        />
      )}
      {deleting && (
        <DeleteTemplateDialog
          open
          template={deleting}
          onOpenChange={(open) => !open && setDeleting(null)}
        />
      )}
    </div>
  );
}
