import {ExternalLink} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {CustomerPanel} from '@/entities/customer';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {Skeleton} from '@/shared/ui/skeleton';
import {useConversationDetail, useCustomer} from '../model/queries';

/** The customer next to the thread on desktop: contacts, consent, team and open deal (CRM-01). */
export function ConversationCustomerPanel({
  conversationId,
}: {
  conversationId: string;
}) {
  const {t} = useTranslation('inbox');
  const detail = useConversationDetail(conversationId);
  const customer = useCustomer(detail.data?.conversation.customer.id);

  if (!customer.data) {
    if (detail.isError || customer.isError) return null;
    return (
      <Card className="gap-3 p-4" aria-busy="true">
        <Skeleton className="h-6 w-40" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-2/3" />
      </Card>
    );
  }
  return (
    <CustomerPanel
      customer={customer.data}
      actions={
        <Button asChild variant="ghost" size="icon">
          <Link
            to={`/customers/${customer.data.id}`}
            aria-label={t('thread.open_customer')}
          >
            <ExternalLink aria-hidden />
          </Link>
        </Button>
      }
    />
  );
}
