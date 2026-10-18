import {useTranslation} from 'react-i18next';
import {UploadImportForm} from '@/features/upload-import';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui/card';
import {PageHeader} from '@/shared/ui/page';
import {ImportStatus} from '@/widgets/import-status';
import {SettingsTabs} from '@/widgets/settings-nav';

/** /settings/import: customers and open leads from a spreadsheet (manager and admin). */
export function SettingsImportPage() {
  const {t} = useTranslation('imports');
  return (
    <>
      <PageHeader title={t('settings:title')} />
      <SettingsTabs />
      <div className="space-y-6">
        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>{t('page.title')}</CardTitle>
            <CardDescription>{t('page.description')}</CardDescription>
          </CardHeader>
          <CardContent>
            <UploadImportForm />
          </CardContent>
        </Card>
        <ImportStatus />
      </div>
    </>
  );
}
