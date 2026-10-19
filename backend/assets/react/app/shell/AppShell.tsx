import {Menu, UserPlus} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, Outlet} from 'react-router';
import {Button} from '@/shared/ui/button';
import {Sheet, SheetContent, SheetHeader, SheetTitle} from '@/shared/ui/sheet';
import {AccountMenu} from './AccountMenu';
import {BottomTabs} from './BottomTabs';
import {NavLinks} from './NavLinks';
import {QuickSearch} from './QuickSearch';

function Brand() {
  const {t} = useTranslation('common');
  return (
    <Link
      to="/today"
      className="flex items-center gap-2 px-3 py-2 font-semibold text-sidebar-foreground"
    >
      <span
        aria-hidden
        className="flex size-7 items-center justify-center rounded-md bg-sidebar-primary text-sm font-bold text-sidebar-primary-foreground"
      >
        M
      </span>
      {t('app_name')}
    </Link>
  );
}

/**
 * Desktop: dark sidebar rail and a top bar with quick search. Tablet: the sidebar collapses into a sheet. Phone:
 * bottom tabs (DESIGN.md "Layout"). Content is capped at 1440 px.
 */
export function AppShell() {
  const {t} = useTranslation('shell');
  const [navOpen, setNavOpen] = useState(false);
  return (
    <div className="min-h-dvh bg-canvas lg:grid lg:grid-cols-[15rem_1fr]">
      <aside className="hidden bg-sidebar p-3 lg:block">
        <div className="sticky top-3 space-y-4">
          <Brand />
          <nav aria-label={t('nav.label')}>
            <NavLinks />
          </nav>
        </div>
      </aside>

      <div className="flex min-w-0 flex-col">
        <header className="sticky top-0 z-30 flex items-center gap-2 border-b bg-background/95 px-4 py-2 backdrop-blur">
          <Button
            variant="ghost"
            size="icon"
            className="hidden sm:inline-flex lg:hidden"
            aria-label={t('toggle_sidebar')}
            onClick={() => setNavOpen(true)}
          >
            <Menu />
          </Button>
          <div className="min-w-0 flex-1">
            <QuickSearch />
          </div>
          <Button asChild className="hidden sm:inline-flex">
            <Link to="/customers?new=1">
              <UserPlus aria-hidden />
              {t('add_customer')}
            </Link>
          </Button>
          <div className="hidden sm:block">
            <AccountMenu />
          </div>
        </header>

        <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 pt-4 pb-24 sm:px-6 sm:pt-6 sm:pb-8">
          <Outlet />
        </main>
      </div>

      <BottomTabs />

      <Sheet open={navOpen} onOpenChange={setNavOpen}>
        <SheetContent side="left" className="w-64 bg-sidebar p-3">
          <SheetHeader className="p-0">
            <SheetTitle className="sr-only">{t('nav.label')}</SheetTitle>
            <Brand />
          </SheetHeader>
          <nav aria-label={t('nav.label')}>
            <NavLinks onNavigate={() => setNavOpen(false)} />
          </nav>
        </SheetContent>
      </Sheet>
    </div>
  );
}
