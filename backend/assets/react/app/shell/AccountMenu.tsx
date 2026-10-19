import {LogOut, Settings} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {ThemeSwitch} from '@/features/switch-theme';
import {Avatar, AvatarFallback} from '@/shared/ui/avatar';
import {Button} from '@/shared/ui/button';
import {Popover, PopoverContent, PopoverTrigger} from '@/shared/ui/popover';
import {useAuth} from '@/entities/user';

function initials(name: string): string {
  return name
    .split(' ')
    .map((part) => part[0] ?? '')
    .join('')
    .slice(0, 2)
    .toUpperCase();
}

/** Theme, settings and sign out: the same items in the desktop popover and the phone's More sheet. */
export function AccountMenuItems({onDone}: {onDone?: () => void}) {
  const {t} = useTranslation('shell');
  const {me, signOut} = useAuth();
  return (
    <div className="space-y-3">
      {me && (
        <div>
          <p className="font-medium">{me.user.name}</p>
          <p className="text-xs text-muted-foreground">{me.dealership.name}</p>
        </div>
      )}
      <div className="space-y-1.5">
        <p className="text-xs font-medium text-muted-foreground">
          {t('account.theme')}
        </p>
        <ThemeSwitch />
      </div>
      <div className="flex flex-col gap-1">
        <Button variant="ghost" className="justify-start" asChild>
          <Link to="/settings" onClick={onDone}>
            <Settings aria-hidden />
            {t('account.settings')}
          </Link>
        </Button>
        <Button
          variant="ghost"
          className="justify-start"
          onClick={async () => {
            // Signing out clears the session; the auth guard then sends the app to /login. (This menu may unmount
            // when its sheet closes, so it must not navigate itself.)
            await signOut();
            onDone?.();
          }}
        >
          <LogOut aria-hidden />
          {t('account.sign_out')}
        </Button>
      </div>
    </div>
  );
}

export function AccountMenu() {
  const {t} = useTranslation('shell');
  const {me} = useAuth();
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('account.menu')}
          className="rounded-full"
        >
          <Avatar className="size-8">
            <AvatarFallback>{initials(me?.user.name ?? '?')}</AvatarFallback>
          </Avatar>
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-72">
        <AccountMenuItems />
      </PopoverContent>
    </Popover>
  );
}
