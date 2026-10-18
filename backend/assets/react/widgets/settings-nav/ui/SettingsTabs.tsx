import {useTranslation} from 'react-i18next';
import {NavLink} from 'react-router';
import {hasRole, useAuth} from '@/entities/user';
import {cn} from '@/shared/lib/utils';

const TABS = [
  {to: '/settings', key: 'profile'},
  {to: '/settings/security', key: 'security'},
  {to: '/settings/users', key: 'users', role: 'admin'},
  {to: '/settings/templates', key: 'templates', role: 'manager'},
  {to: '/settings/import', key: 'import', role: 'manager'},
  {to: '/settings/routing', key: 'routing', role: 'manager'},
  {to: '/settings/suppressions', key: 'suppressions'},
  {to: '/settings/audit', key: 'audit', role: 'admin'},
] as const;

/** Links between the settings screens; every settings page renders it under its header. */
export function SettingsTabs() {
  const {t} = useTranslation('settings');
  const {me} = useAuth();
  return (
    <nav
      aria-label={t('title')}
      className="mb-4 flex gap-1 overflow-x-auto border-b pb-px"
    >
      {TABS.filter((tab) => !('role' in tab) || hasRole(me, tab.role)).map(
        (tab) => (
          <NavLink
            key={tab.to}
            to={tab.to}
            end
            className={({isActive}) =>
              cn(
                'flex min-h-11 items-center border-b-2 border-transparent px-3 text-sm font-medium whitespace-nowrap text-muted-foreground sm:min-h-9',
                isActive && 'border-primary text-foreground',
              )
            }
          >
            {t(`tabs.${tab.key}`)}
          </NavLink>
        ),
      )}
    </nav>
  );
}
