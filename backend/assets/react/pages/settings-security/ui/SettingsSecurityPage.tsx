import {useQuery} from '@tanstack/react-query';
import {ShieldAlert, ShieldCheck} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui/card';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';
import {SettingsTabs} from '@/widgets/settings-nav';
import {fetchMfaStatus, mfaStatusKey} from '../api';

/** Two or fewer recovery codes left is worth a warning. */
const LOW_CODES = 2;

/** The Security tab: the signed-in user's two-step sign-in and what to do when the phone is lost. */
export function SettingsSecurityPage() {
  const {t} = useTranslation('settings');
  return (
    <>
      <PageHeader title={t('title')} />
      <SettingsTabs />
      <div className="grid gap-4 lg:grid-cols-2">
        <MfaCard />
      </div>
    </>
  );
}

function MfaCard() {
  const {t} = useTranslation('auth');
  const {t: tc} = useTranslation('common');
  const status = useQuery({queryKey: mfaStatusKey, queryFn: fetchMfaStatus});

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {t('mfa.security.title')}
          {status.data &&
            (status.data.enrolled ? (
              <Badge className="bg-success-surface text-success">
                <ShieldCheck aria-hidden />
                {t('mfa.security.on')}
              </Badge>
            ) : (
              <Badge className="bg-warning-surface text-warning">
                <ShieldAlert aria-hidden />
                {t('mfa.security.off')}
              </Badge>
            ))}
        </CardTitle>
        <CardDescription>{t('mfa.security.description')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3 text-sm">
        {status.isPending && (
          <div className="space-y-2" aria-busy="true">
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-4 w-full" />
          </div>
        )}
        {status.isError && (
          <div className="space-y-2">
            <p role="alert" className="text-danger">
              {t('mfa.security.load_error')}
            </p>
            <Button
              type="button"
              variant="outline"
              onClick={() => void status.refetch()}
            >
              {tc('action.retry')}
            </Button>
          </div>
        )}
        {status.data && !status.data.enrolled && (
          <p>{t('mfa.security.off_help')}</p>
        )}
        {status.data?.enrolled && (
          <>
            <p>
              {t('mfa.security.codes_left', {
                count: status.data.recovery_codes_remaining,
              })}
            </p>
            {status.data.recovery_codes_remaining <= LOW_CODES && (
              <p className="rounded-md bg-warning-surface p-3 text-warning">
                {t('mfa.security.codes_low')}
              </p>
            )}
          </>
        )}
        <p className="text-muted-foreground">{t('mfa.security.lost_phone')}</p>
      </CardContent>
    </Card>
  );
}
