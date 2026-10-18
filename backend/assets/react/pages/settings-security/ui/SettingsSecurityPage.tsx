import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "mfa" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsSecurityPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.security')} item="mfa" />;
}
