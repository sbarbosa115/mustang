import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {useSearchParams} from 'react-router';
import {hasRole, listUsers, useAuth, usersKeys} from '@/entities/user';
import {PageHeader} from '@/shared/ui/page';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {ContactNowList} from '@/widgets/contact-now-list';

const REP_ROLES = ['sales', 'bdc', 'manager'];
const MINE = 'mine';

/**
 * /today: the signed-in user's "contact now" list. A manager can switch to any rep's list (?user_id=, also the links
 * from /team).
 */
export function TodayPage() {
  const {t} = useTranslation('today');
  const {me} = useAuth();
  const manager = hasRole(me, 'manager');
  const [params, setParams] = useSearchParams();
  const requested = params.get('user_id');
  const userId =
    manager && requested && requested !== me?.user.id ? requested : null;

  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
    staleTime: 5 * 60_000,
    enabled: manager,
  });
  const reps = (users.data?.items ?? []).filter(
    (user) =>
      user.active && REP_ROLES.includes(user.role) && user.id !== me?.user.id,
  );
  const viewing = userId ? reps.find((rep) => rep.id === userId) : undefined;

  const pick = (value: string) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (value === MINE) next.delete('user_id');
        else next.set('user_id', value);
        return next;
      },
      {replace: true},
    );

  return (
    <>
      <PageHeader
        title={t('title')}
        description={
          viewing ? t('rep.viewing', {name: viewing.name}) : t('description')
        }
        actions={
          manager && (
            <Select value={userId ?? MINE} onValueChange={pick}>
              <SelectTrigger
                aria-label={t('rep.label')}
                className="w-full sm:w-56"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={MINE}>{t('rep.mine')}</SelectItem>
                {reps.map((rep) => (
                  <SelectItem key={rep.id} value={rep.id}>
                    {t('rep.option', {name: rep.name})}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )
        }
      />
      <ContactNowList key={userId ?? MINE} userId={userId} />
    </>
  );
}
