import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "data-import" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsImportPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.import')} item="data-import" />;
}
