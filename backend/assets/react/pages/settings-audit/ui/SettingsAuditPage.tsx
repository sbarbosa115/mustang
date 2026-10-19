import {ScrollText} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {PageHeader} from '@/shared/ui/page';
import {AuditTable} from '@/widgets/audit-table';
import {SettingsTabs} from '@/widgets/settings-nav';

/** /settings/audit: every change and customer record access of the dealership (admin, NFR-04). */
export function SettingsAuditPage() {
  const {t} = useTranslation('audit');
  return (
    <>
      <PageHeader title={t('settings:title')} />
      <SettingsTabs />
      <div className="mb-4 space-y-1">
        <h2 className="flex items-center gap-2 text-lg font-semibold">
          <ScrollText aria-hidden className="size-5" /> {t('title')}
        </h2>
        <p className="text-sm text-muted-foreground">{t('description')}</p>
      </div>
      <AuditTable />
    </>
  );
}
