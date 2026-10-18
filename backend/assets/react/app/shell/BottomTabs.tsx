import {MoreHorizontal} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {NavLink} from 'react-router';
import {cn} from '@/shared/lib/utils';
import {Sheet, SheetContent, SheetHeader, SheetTitle} from '@/shared/ui/sheet';
import {AccountMenuItems} from './AccountMenu';
import {useVisibleNav} from './NavLinks';

/** Phone navigation: Today, Inbox, Customers, Inventory and More (DESIGN.md "Layout"). */
export function BottomTabs() {
  const {t} = useTranslation('shell');
  const [moreOpen, setMoreOpen] = useState(false);
  const items = useVisibleNav();
  const tabs = items.filter((item) => item.tab);
  const more = items.filter((item) => !item.tab);
  const tabClass = ({isActive}: {isActive: boolean}) =>
    cn(
      'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 text-xs font-medium text-muted-foreground',
      isActive && 'text-primary',
    );
  return (
    <nav
      aria-label={t('nav.label')}
      className="fixed inset-x-0 bottom-0 z-40 border-t bg-background pb-[env(safe-area-inset-bottom)] sm:hidden"
    >
      <ul className="flex">
        {tabs.map(({to, labelKey, icon: Icon}) => (
          <li key={to} className="flex flex-1">
            <NavLink to={to} className={tabClass}>
              <Icon aria-hidden className="size-5" />
              {t(labelKey)}
            </NavLink>
          </li>
        ))}
        <li className="flex flex-1">
          <button
            type="button"
            onClick={() => setMoreOpen(true)}
            className={tabClass({isActive: false})}
          >
            <MoreHorizontal aria-hidden className="size-5" />
            {t('nav.more')}
          </button>
        </li>
      </ul>
      <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
        <SheetContent side="bottom" className="rounded-t-xl">
          <SheetHeader>
            <SheetTitle>{t('nav.more')}</SheetTitle>
          </SheetHeader>
          <ul className="grid gap-1 px-4">
            {more.map(({to, labelKey, icon: Icon}) => (
              <li key={to}>
                <NavLink
                  to={to}
                  onClick={() => setMoreOpen(false)}
                  className="flex min-h-11 items-center gap-3 rounded-md px-3 hover:bg-accent"
                >
                  <Icon aria-hidden className="size-5" />
                  {t(labelKey)}
                </NavLink>
              </li>
            ))}
          </ul>
          <div className="border-t px-4 py-3">
            <AccountMenuItems onDone={() => setMoreOpen(false)} />
          </div>
        </SheetContent>
      </Sheet>
    </nav>
  );
}
