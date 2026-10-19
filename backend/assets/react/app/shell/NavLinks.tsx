import {useTranslation} from 'react-i18next';
import {NavLink} from 'react-router';
import {hasRole} from '@/entities/user';
import {cn} from '@/shared/lib/utils';
import {useAuth} from '@/entities/user';
import {NAV, type NavItem} from './nav';

export function useVisibleNav(): NavItem[] {
  const {me} = useAuth();
  return NAV.filter((item) => !item.role || hasRole(me, item.role));
}

/** The sidebar's links: dark rail on desktop, the same list in the tablet sheet. */
export function NavLinks({onNavigate}: {onNavigate?: () => void}) {
  const {t} = useTranslation('shell');
  const items = useVisibleNav();
  return (
    <ul className="space-y-1">
      {items.map(({to, labelKey, icon: Icon}) => (
        <li key={to}>
          <NavLink
            to={to}
            onClick={onNavigate}
            className={({isActive}) =>
              cn(
                'flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-sidebar-foreground transition-colors hover:bg-sidebar-accent lg:min-h-9',
                isActive && 'bg-sidebar-accent text-sidebar-accent-foreground',
              )
            }
          >
            <Icon aria-hidden className="size-4" />
            {t(labelKey)}
          </NavLink>
        </li>
      ))}
    </ul>
  );
}
