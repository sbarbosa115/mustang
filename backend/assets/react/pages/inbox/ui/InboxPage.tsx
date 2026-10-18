import {MessagesSquare} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {SimulateInbound} from '@/features/simulate-inbound';
import {Card} from '@/shared/ui/card';
import {EmptyState} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {ConversationList} from '@/widgets/conversation-list';

/**
 * /inbox: folders, the rep filter and the conversations. On a phone that is the whole screen and a conversation
 * opens full screen at /inbox/:conversationId; on a desktop the thread opens next to the list.
 */
export function InboxPage() {
  const {t} = useTranslation('inbox');
  return (
    <div className="lg:grid lg:h-[calc(100dvh-7rem)] lg:grid-cols-[22rem_minmax(0,1fr)] lg:gap-4">
      <div className="min-h-0 lg:overflow-y-auto lg:pr-1">
        <PageHeader title={t('title')} actions={<SimulateInbound />} />
        <ConversationList />
      </div>
      <Card className="hidden lg:flex lg:items-center lg:justify-center">
        <EmptyState icon={MessagesSquare} title={t('pick.title')} />
      </Card>
    </div>
  );
}
