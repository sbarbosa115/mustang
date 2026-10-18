import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "audit-log" builds this page (docs/pdr/prd-mvp.md). */
export function SettingsAuditPage() {
  const {t} = useTranslation('settings');
  return <PagePlaceholder title={t('tabs.audit')} item="audit-log" />;
}
