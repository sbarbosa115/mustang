import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "dashboard" builds this page (docs/pdr/prd-mvp.md). */
export function DashboardPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.dashboard')} item="dashboard" />;
}
