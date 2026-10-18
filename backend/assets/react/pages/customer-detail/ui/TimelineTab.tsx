import {History} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Card} from '@/shared/ui/card';
import {EmptyState} from '@/shared/ui/mustang';

/**
 * The slot for item "timeline": replace the body of this component with the customer-timeline widget
 * (docs/pdr/prd-mvp.md, item 13). Until then it says what will appear.
 */
export function TimelineTab({customerId}: {customerId: string}) {
  const {t} = useTranslation('customers');
  return (
    <Card data-customer-id={customerId}>
      <EmptyState icon={History} title={t('detail.timeline_pending')} />
    </Card>
  );
}
