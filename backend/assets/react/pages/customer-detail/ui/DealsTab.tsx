import {useQuery} from '@tanstack/react-query';
import {Handshake} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {DealSummary, dealsKeys, listDeals} from '@/entities/deal';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {EmptyState} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';

/** The customer's deals, newest first, each linking to the deal. */
export function DealsTab({customerId}: {customerId: string}) {
  const {t} = useTranslation('customers');
  const query = {customer_id: customerId, per_page: 50};
  const deals = useQuery({
    queryKey: dealsKeys.list(query),
    queryFn: () => listDeals(query),
  });

  if (deals.isLoading) {
    return (
      <Card
        className="gap-2 p-4"
        aria-busy="true"
        aria-label={t('common:list.loading')}
      >
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
      </Card>
    );
  }
  if (deals.isError) {
    return (
      <Card>
        <EmptyState
          icon={Handshake}
          title={t('detail.deals_error')}
          action={
            <Button variant="outline" onClick={() => void deals.refetch()}>
              {t('common:action.retry')}
            </Button>
          }
        />
      </Card>
    );
  }
  if (!deals.data || deals.data.items.length === 0) {
    return (
      <Card>
        <EmptyState icon={Handshake} title={t('detail.deals_empty')} />
      </Card>
    );
  }
  return (
    <Card className="gap-0 py-0">
      <ul className="divide-y">
        {deals.data.items.map((deal) => (
          <li key={deal.id}>
            <Link to={`/deals/${deal.id}`} className="block p-4 hover:bg-muted">
              <DealSummary deal={deal} />
            </Link>
          </li>
        ))}
      </ul>
    </Card>
  );
}
