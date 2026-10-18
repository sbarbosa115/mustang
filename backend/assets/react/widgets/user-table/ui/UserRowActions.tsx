import {KeyRound, MoreHorizontal, Pencil, UserCheck, UserX} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@/entities/user';
import {EditUserDialog, ResetMfaDialog} from '@/features/edit-user';
import {
  DeactivateUserDialog,
  useActivateUser,
} from '@/features/toggle-user-active';
import type {Schema} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/ui/dropdown-menu';

type Dialog = 'edit' | 'deactivate' | 'mfa' | null;

/** The row menu: edit, activate or deactivate, reset two-step sign-in. Your own row cannot be deactivated. */
export function UserRowActions({user}: {user: Schema<'UserOutput'>}) {
  const {t} = useTranslation('users');
  const {me} = useAuth();
  const [dialog, setDialog] = useState<Dialog>(null);
  const activate = useActivateUser(user);
  const isSelf = me?.user.id === user.id;
  const close = (open: boolean) => {
    if (!open) setDialog(null);
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="outline"
            size="icon"
            aria-label={t('list.actions', {name: user.name})}
            onClick={(event) => event.stopPropagation()}
          >
            <MoreHorizontal />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onSelect={() => setDialog('edit')}>
            <Pencil /> {t('edit.action')}
          </DropdownMenuItem>
          <DropdownMenuItem
            disabled={!user.mfa_enrolled}
            onSelect={() => setDialog('mfa')}
          >
            <KeyRound /> {t('mfa.action')}
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          {user.active ? (
            <DropdownMenuItem
              variant="destructive"
              disabled={isSelf}
              onSelect={() => setDialog('deactivate')}
            >
              <UserX /> {t('deactivate.action')}
            </DropdownMenuItem>
          ) : (
            <DropdownMenuItem
              disabled={activate.isPending}
              onSelect={() => activate.mutate()}
            >
              <UserCheck /> {t('activate.action')}
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
      {dialog === 'edit' && (
        <EditUserDialog user={user} open onOpenChange={close} />
      )}
      {dialog === 'mfa' && (
        <ResetMfaDialog user={user} open onOpenChange={close} />
      )}
      {dialog === 'deactivate' && (
        <DeactivateUserDialog user={user} open onOpenChange={close} />
      )}
    </>
  );
}
