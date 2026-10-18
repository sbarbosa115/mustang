import {Plus, Users} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CreateUserDialog} from '@/features/create-user';
import {Button} from '@/shared/ui/button';
import {PageHeader} from '@/shared/ui/page';
import {UserTable} from '@/widgets/user-table';
import {SettingsTabs} from '@/widgets/settings-nav';

/** /settings/users: add, edit, deactivate users and reset their two-step sign-in (admin). */
export function SettingsUsersPage() {
  const {t} = useTranslation('users');
  const [creating, setCreating] = useState(false);
  const add = (
    <Button onClick={() => setCreating(true)}>
      <Plus /> {t('create.action')}
    </Button>
  );
  return (
    <>
      <PageHeader title={t('settings:title')} />
      <SettingsTabs />
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div className="space-y-1">
          <h2 className="flex items-center gap-2 text-lg font-semibold">
            <Users aria-hidden className="size-5" /> {t('title')}
          </h2>
          <p className="text-sm text-muted-foreground">{t('description')}</p>
        </div>
        {add}
      </div>
      <UserTable />
      <CreateUserDialog open={creating} onOpenChange={setCreating} />
    </>
  );
}
