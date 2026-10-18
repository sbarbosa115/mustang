import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "compliance" builds this page (docs/pdr/prd-mvp.md). */
export function CustomerConsentPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.customers')} item="compliance" />;
}
