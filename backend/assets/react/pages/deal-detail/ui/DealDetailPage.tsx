import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "deals" builds this page (docs/pdr/prd-mvp.md). */
export function DealDetailPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.deals')} item="deals" />;
}
