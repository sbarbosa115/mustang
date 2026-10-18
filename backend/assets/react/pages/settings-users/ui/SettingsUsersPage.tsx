import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "users-admin" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsUsersPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.users')} item="users-admin" />;
}
