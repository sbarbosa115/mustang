import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "inventory" builds this page (docs/pdr/prd-mvp.md). */
export function VehicleDetailPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.inventory')} item="inventory" />;
}
