import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "templates" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsTemplatesPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.templates')} item="templates" />;
}
