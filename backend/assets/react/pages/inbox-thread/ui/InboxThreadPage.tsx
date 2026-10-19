import {useTranslation} from 'react-i18next';
import {useParams, useSearchParams} from 'react-router';
import {SimulateInbound} from '@/features/simulate-inbound';
import {PageHeader} from '@/shared/ui/page';
import {ConversationList} from '@/widgets/conversation-list';
import {
  ConversationCustomerPanel,
  ConversationThread,
} from '@/widgets/conversation-thread';

/**
 * /inbox/:conversationId. Phone: the thread full screen with a way back to the list. Desktop: folders and list,
 * the thread and the customer panel side by side (DESIGN.md "Layout").
 */
export function InboxThreadPage() {
  const {t} = useTranslation('inbox');
  const {conversationId = ''} = useParams();
  const [params] = useSearchParams();
  const search = params.toString();
  const backHref = `/inbox${search ? `?${search}` : ''}`;

  return (
    <div className="h-[calc(100dvh-10.5rem)] sm:h-[calc(100dvh-7rem)] lg:grid lg:grid-cols-[22rem_minmax(0,1fr)] lg:gap-4 xl:grid-cols-[22rem_minmax(0,1fr)_18rem]">
      <div className="hidden min-h-0 overflow-y-auto pr-1 lg:block">
        <PageHeader title={t('title')} actions={<SimulateInbound />} />
        <ConversationList selectedId={conversationId} />
      </div>
      <div className="h-full min-h-0">
        <ConversationThread
          key={conversationId}
          conversationId={conversationId}
          backHref={backHref}
        />
      </div>
      <aside className="hidden min-h-0 overflow-y-auto xl:block">
        <ConversationCustomerPanel conversationId={conversationId} />
      </aside>
    </div>
  );
}
