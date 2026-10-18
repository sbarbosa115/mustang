import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {templatesKeys, type Template} from '@/entities/template';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {TemplateForm} from './TemplateForm';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Given when editing; left out to create. */
  template?: Template;
}

/** "New template" and "Edit template" as one dialog. */
export function TemplateDialog({open, onOpenChange, template}: Props) {
  const {t} = useTranslation('templates');
  const queryClient = useQueryClient();
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>
            {template ? t('form.edit_title') : t('form.create_title')}
          </DialogTitle>
          <DialogDescription>{t('form.description')}</DialogDescription>
        </DialogHeader>
        <TemplateForm
          key={template?.id ?? 'new'}
          template={template}
          onCancel={() => onOpenChange(false)}
          onSaved={(saved) => {
            void queryClient.invalidateQueries({queryKey: templatesKeys.all});
            toast.success(t('saved', {name: saved.name}));
            onOpenChange(false);
          }}
        />
      </DialogContent>
    </Dialog>
  );
}
