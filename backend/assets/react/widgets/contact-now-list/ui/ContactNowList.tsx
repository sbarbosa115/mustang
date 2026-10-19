import {useMutation, useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Flame, Inbox, PartyPopper} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {toast} from 'sonner';
import {fetchCustomerConversation} from '@/entities/conversation';
import {useAuth} from '@/entities/user';
import {fetchContactNow, worklistKeys} from '@/entities/worklist-item';
import type {Schema} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {DataTable, type Channel} from '@/shared/ui/mustang';
import {useDailyProgress} from '../model/progress';
import {ContactNowRow} from './ContactNowRow';

type Item = Schema<'WorklistItemOutput'>;

interface Props {
  /** Another rep's list (managers only); the signed-in user's own when left out. */
  userId?: string | null;
}

/**
 * The "contact now" list: one row per customer with the reason to act, the most urgent first, and how much of today's
 * list is done. A text or email opens the customer's thread in the inbox; a call dials the phone.
 */
export function ContactNowList({userId}: Props) {
  const {t} = useTranslation('today');
  const {me} = useAuth();
  const navigate = useNavigate();
  const timeZone = me?.dealership.timezone;
  const query = userId ? {user_id: userId} : {};
  const result = useQuery({
    queryKey: worklistKeys.list(query),
    queryFn: () => fetchContactNow(query),
    refetchInterval: 60_000,
  });
  const items = result.data?.items;
  const progress = useDailyProgress(
    result.data?.user.id,
    timeZone,
    items?.map((item) => item.customer.id),
  );
  const hot = items?.filter((item) => item.hot).length ?? 0;

  const open = useMutation({
    mutationFn: (customerId: string) => fetchCustomerConversation(customerId),
    onSuccess: (conversation) =>
      void navigate(`/inbox/${conversation.conversation.id}`),
    onError: () => toast.error(t('open_error')),
  });
  const contact = (item: Item, channel: Channel) => {
    if (channel !== 'call') open.mutate(item.customer.id);
  };

  const row = (item: Item) => (
    <ContactNowRow item={item} timeZone={timeZone} onContact={contact} />
  );
  const columns: Array<ColumnDef<Item, unknown>> = [
    {
      id: 'customer',
      header: t('list.column'),
      cell: ({row: r}) => row(r.original),
    },
  ];

  return (
    <section aria-label={t('list.label')} className="min-w-0 space-y-3">
      {items && progress && (
        <div className="space-y-2 rounded-lg border bg-card p-4">
          <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
            <span className="font-medium">
              {t('progress.remaining', {count: items.length})}
            </span>
            {hot > 0 && (
              <span className="inline-flex items-center gap-1 text-hot">
                <Flame aria-hidden className="size-3.5" />
                {t('progress.hot', {count: hot})}
              </span>
            )}
            <span className="tabular text-muted-foreground">
              {t('progress.text', {done: progress.done, count: progress.total})}
            </span>
          </div>
          <div
            role="progressbar"
            aria-label={t('progress.label')}
            aria-valuemin={0}
            aria-valuemax={progress.total}
            aria-valuenow={progress.done}
            className="h-2 overflow-hidden rounded-full bg-muted"
          >
            <div
              className="h-full rounded-full bg-primary"
              style={{
                width: `${progress.total ? (100 * progress.done) / progress.total : 0}%`,
              }}
            />
          </div>
        </div>
      )}
      <DataTable
        columns={columns}
        data={items}
        isLoading={result.isLoading}
        error={result.error}
        onRetry={() => void result.refetch()}
        empty={{
          icon: items && progress && progress.done > 0 ? PartyPopper : Inbox,
          title: t('list.empty'),
          action: (
            <Button asChild variant="outline">
              <Link to="/inbox">{t('list.open_inbox')}</Link>
            </Button>
          ),
        }}
        getRowId={(item) => item.customer.id}
        renderCard={row}
      />
    </section>
  );
}
