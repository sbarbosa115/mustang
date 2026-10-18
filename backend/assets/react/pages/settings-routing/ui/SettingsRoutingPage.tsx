import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "leads" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsRoutingPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.routing')} item="leads" />;
}
