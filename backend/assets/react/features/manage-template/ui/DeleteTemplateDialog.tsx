import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
  deleteTemplate,
  templatesKeys,
  type Template,
} from '@/entities/template';
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
  template: Template;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Deleting a template asks first and names it (DESIGN.md: destructive actions confirm). */
export function DeleteTemplateDialog({template, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('templates');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function remove() {
    setBusy(true);
    setError(null);
    try {
      await deleteTemplate(template.id);
      void queryClient.invalidateQueries({queryKey: templatesKeys.all});
      toast.success(t('delete.deleted', {name: template.name}));
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
          <AlertDialogTitle>
            {t('delete.title', {name: template.name})}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {t('delete.description')}
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
            onClick={() => void remove()}
          >
            {t('delete.confirm')}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
