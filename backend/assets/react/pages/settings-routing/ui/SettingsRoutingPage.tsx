import {useQuery} from '@tanstack/react-query';
import {Shuffle} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {fetchRouting} from '@/entities/lead';
import {listUsers, usersKeys} from '@/entities/user';
import {Button} from '@/shared/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui/card';
import {EmptyState} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';
import {SettingsTabs} from '@/widgets/settings-nav';
import {ROUTING_KEY, RoutingEditor} from './RoutingEditor';

/** /settings/routing: the round-robin order new leads follow (manager). */
export function SettingsRoutingPage() {
  const {t} = useTranslation('leads');
  const routing = useQuery({queryKey: ROUTING_KEY, queryFn: fetchRouting});
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
  });
  const sales = (users.data?.items ?? []).filter(
    (u) => u.active && u.role === 'sales',
  );
  const loading = routing.isLoading || users.isLoading;
  const failed = routing.isError || users.isError;

  return (
    <>
      <PageHeader title={t('settings:title')} />
      <SettingsTabs />
      <Card className="max-w-2xl">
        <CardHeader>
          <CardTitle>{t('routing.title')}</CardTitle>
          <CardDescription>{t('routing.description')}</CardDescription>
        </CardHeader>
        <CardContent>
          {loading && (
            <div
              className="space-y-2"
              aria-busy="true"
              aria-label={t('common:list.loading')}
            >
              {[0, 1, 2].map((i) => (
                <Skeleton key={i} className="h-11 w-full" />
              ))}
            </div>
          )}
          {!loading && failed && (
            <EmptyState
              icon={Shuffle}
              title={t('common:list.error')}
              action={
                <Button
                  variant="outline"
                  onClick={() => {
                    void routing.refetch();
                    void users.refetch();
                  }}
                >
                  {t('common:action.retry')}
                </Button>
              }
            />
          )}
          {!loading && !failed && routing.data && sales.length === 0 && (
            <EmptyState icon={Shuffle} title={t('routing.no_sales')} />
          )}
          {!loading && !failed && routing.data && sales.length > 0 && (
            <RoutingEditor routing={routing.data} sales={sales} />
          )}
        </CardContent>
      </Card>
    </>
  );
}
