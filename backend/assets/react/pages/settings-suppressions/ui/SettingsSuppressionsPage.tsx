import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "compliance" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsSuppressionsPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.suppressions')} item="compliance" />;
}
