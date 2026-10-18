import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {useAuth} from '@/entities/user';
import {ThemeSwitch} from '@/features/switch-theme';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui/card';
import {PageHeader} from '@/shared/ui/page';
import {SettingsTabs} from '@/widgets/settings-nav';

/** The signed-in user's profile and theme. */
export function SettingsPage() {
  const {t} = useTranslation('settings');
  const {me} = useAuth();
  if (!me) return null;
  return (
    <>
      <PageHeader title={t('title')} />
      <SettingsTabs />
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t('profile.title')}</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
              <dt className="text-muted-foreground">{t('profile.name')}</dt>
              <dd>{me.user.name}</dd>
              <dt className="text-muted-foreground">{t('profile.email')}</dt>
              <dd>{me.user.email}</dd>
              <dt className="text-muted-foreground">{t('profile.role')}</dt>
              <dd>{t(`role.${me.user.role}`)}</dd>
              <dt className="text-muted-foreground">
                {t('profile.dealership')}
              </dt>
              <dd>{me.dealership.name}</dd>
            </dl>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t('profile.theme')}</CardTitle>
            <CardDescription>{t('profile.theme_help')}</CardDescription>
          </CardHeader>
          <CardContent>
            <ThemeSwitch onSaved={() => toast.success(t('profile.saved'))} />
          </CardContent>
        </Card>
      </div>
    </>
  );
}
