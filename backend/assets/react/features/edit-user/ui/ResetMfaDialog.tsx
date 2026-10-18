import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {resetUserMfa, usersKeys} from '@/entities/user';
import type {Schema} from '@/shared/api';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import {Button} from '@/shared/ui/button';
import {errorText} from '../lib/errors';

interface Props {
  user: Schema<'UserOutput'>;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Switching off someone's second factor asks first and names them; they enroll again at their next sign-in. */
export function ResetMfaDialog({user, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('users');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function reset() {
    setBusy(true);
    setError(null);
    try {
      await resetUserMfa(user.id);
      void queryClient.invalidateQueries({queryKey: usersKeys.all});
      toast.success(t('mfa.done', {name: user.name}));
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('mfa.title', {name: user.name})}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('mfa.description', {name: user.name})}
          </AlertDialogDescription>
        </AlertDialogHeader>
        {error && (
          <p role="alert" className="text-sm text-danger">
            {error}
          </p>
        )}
        <AlertDialogFooter>
          <AlertDialogCancel>{t('common:action.cancel')}</AlertDialogCancel>
          <Button
            variant="destructive"
            disabled={busy}
            onClick={() => void reset()}
          >
            {t('mfa.confirm', {name: user.name})}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
