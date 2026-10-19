import {useTranslation} from 'react-i18next';
import {PageHeader} from '@/shared/ui/page';
import {InterventionQueue} from '@/widgets/intervention-queue';

/** /team (managers): the deals that need a manager today, and who has customers waiting. */
export function TeamPage() {
  const {t} = useTranslation('team');
  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      <InterventionQueue />
    </>
  );
}
