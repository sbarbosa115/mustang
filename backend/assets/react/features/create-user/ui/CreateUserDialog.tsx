import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {usersKeys} from '@/entities/user';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {UserForm} from './UserForm';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** "Add user" as a dialog opened from the Users page header and its empty state. */
export function CreateUserDialog({open, onOpenChange}: Props) {
  const {t} = useTranslation('users');
  const queryClient = useQueryClient();
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('create.title')}</DialogTitle>
          <DialogDescription>{t('create.description')}</DialogDescription>
        </DialogHeader>
        <UserForm
          onCancel={() => onOpenChange(false)}
          onCreated={(user) => {
            void queryClient.invalidateQueries({queryKey: usersKeys.all});
            toast.success(t('create.created', {name: user.name}));
            onOpenChange(false);
          }}
        />
      </DialogContent>
    </Dialog>
  );
}
