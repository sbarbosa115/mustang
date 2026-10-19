import {Plus} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {hasRole, useAuth} from '@/entities/user';
import {TemplateDialog} from '@/features/manage-template';
import {Button} from '@/shared/ui/button';
import {PageHeader} from '@/shared/ui/page';
import {SettingsTabs} from '@/widgets/settings-nav';
import {TemplateList} from '@/widgets/template-list';

/** /settings/templates: the texts and emails reps pick in the inbox; managers write them. */
export function SettingsTemplatesPage() {
  const {t} = useTranslation('templates');
  const {me} = useAuth();
  const canManage = hasRole(me, 'manager');
  const [creating, setCreating] = useState(false);
  const create = (variant: 'default' | 'outline') => (
    <Button variant={variant} onClick={() => setCreating(true)}>
      <Plus /> {t('list.new')}
    </Button>
  );

  return (
    <>
      <PageHeader
        title={t('settings:title')}
        actions={canManage ? create('default') : undefined}
      />
      <SettingsTabs />
      <p className="mb-4 max-w-2xl text-sm text-muted-foreground">
        {t('page.description')}
      </p>
      <TemplateList
        canManage={canManage}
        emptyAction={canManage ? create('outline') : undefined}
      />
      {creating && (
        <TemplateDialog
          open
          onOpenChange={(open) => !open && setCreating(false)}
        />
      )}
    </>
  );
}
