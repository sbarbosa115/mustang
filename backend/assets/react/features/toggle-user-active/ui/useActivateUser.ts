import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {activateUser, usersKeys} from '@/entities/user';
import type {Schema} from '@/shared/api';
import {errorText} from '../lib/errors';

/** Reactivating is safe, so it needs no confirmation: a menu item that runs at once and says what happened. */
export function useActivateUser(user: Schema<'UserOutput'>) {
  const {t, i18n} = useTranslation('users');
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => activateUser(user.id),
    onSuccess: () => {
      void queryClient.invalidateQueries({queryKey: usersKeys.all});
      toast.success(t('activate.done', {name: user.name}));
    },
    onError: (e) => toast.error(errorText(t, (k) => i18n.exists(k), e)),
  });
}
