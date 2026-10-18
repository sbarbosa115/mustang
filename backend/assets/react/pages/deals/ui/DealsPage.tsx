import {Plus} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/entities/user';
import {CreateDealDialog} from '@/features/create-deal';
import {Button} from '@/shared/ui/button';
import {PageHeader} from '@/shared/ui/page';
import {DealBoard} from '@/widgets/deal-board';

/** The deals board: every open deal by stage, and the delivered and lost ones (CRM-04). */
export function DealsPage() {
  const {t} = useTranslation('deals');
  const {me} = useAuth();
  const [creating, setCreating] = useState(false);
  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Button onClick={() => setCreating(true)}>
            <Plus aria-hidden />
            {t('new_deal')}
          </Button>
        }
      />
      <DealBoard userId={me?.user.id} />
      <CreateDealDialog open={creating} onOpenChange={setCreating} />
    </>
  );
}
