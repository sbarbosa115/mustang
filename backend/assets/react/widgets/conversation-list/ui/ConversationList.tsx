import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Inbox} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {
  conversationsKeys,
  listConversations,
  type Conversation,
} from '@/entities/conversation';
import {useAuth} from '@/entities/user';
import {
  ConversationFilters,
  useConversationFilters,
} from '@/features/filter-conversations';
import {DataTable, Pagination} from '@/shared/ui/mustang';
import {ConversationRow} from './ConversationRow';

const PER_PAGE = 25;

interface Props {
  /** The conversation open next to the list, highlighted. */
  selectedId?: string;
}

/**
 * The inbox list: folders with counts (all, unreplied, fumbled), the per-rep filter, search and the conversations,
 * most recent first. Filters live in the URL and travel with the links into a thread.
 */
export function ConversationList({selectedId}: Props) {
  const {t} = useTranslation('inbox');
  const {me} = useAuth();
  const [params] = useSearchParams();
  const filters = useConversationFilters();
  const timeZone = me?.dealership.timezone;

  const query = {
    page: filters.page,
    per_page: PER_PAGE,
    folder: filters.folder,
    ...(filters.userId ? {user_id: filters.userId} : {}),
    ...(filters.q ? {q: filters.q} : {}),
  };
  const result = useQuery({
    queryKey: conversationsKeys.list(query),
    queryFn: () => listConversations(query),
    refetchInterval: 15_000,
  });

  const search = params.toString();
  const hrefOf = (id: string) => `/inbox/${id}${search ? `?${search}` : ''}`;
  const row = (conversation: Conversation) => (
    <ConversationRow
      conversation={conversation}
      href={hrefOf(conversation.id)}
      selected={conversation.id === selectedId}
      timeZone={timeZone}
    />
  );
  const columns: Array<ColumnDef<Conversation, unknown>> = [
    {
      id: 'conversation',
      header: t('list.column'),
      cell: ({row: r}) => row(r.original),
    },
  ];

  return (
    <section aria-label={t('list.label')} className="min-w-0 space-y-3">
      <ConversationFilters filters={filters} />
      <DataTable
        columns={columns}
        data={result.data?.items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        filtered={filters.filtered}
        onClearFilters={filters.clear}
        empty={{icon: Inbox, title: t(`list.empty.${filters.folder}`)}}
        getRowId={(c) => c.id}
        renderCard={(c) => (
          <div className="rounded-lg border bg-card shadow-xs">{row(c)}</div>
        )}
        footer={
          result.data &&
          result.data.total > PER_PAGE && (
            <Pagination
              page={filters.page}
              perPage={PER_PAGE}
              total={result.data.total}
              onPage={filters.setPage}
            />
          )
        }
      />
    </section>
  );
}
