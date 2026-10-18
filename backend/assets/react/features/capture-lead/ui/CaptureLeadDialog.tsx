import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {customersKeys} from '@/entities/customer';
import {leadsKeys, type Lead} from '@/entities/lead';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {CaptureLeadForm} from './CaptureLeadForm';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCaptured: (lead: Lead) => void;
}

/** "Capture lead" as a dialog, opened from the leads list (and by /leads?new=1). */
export function CaptureLeadDialog({open, onOpenChange, onCaptured}: Props) {
  const {t} = useTranslation('leads');
  const queryClient = useQueryClient();
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('capture.title')}</DialogTitle>
          <DialogDescription>{t('capture.description')}</DialogDescription>
        </DialogHeader>
        <CaptureLeadForm
          onCancel={() => onOpenChange(false)}
          onCaptured={(lead) => {
            void queryClient.invalidateQueries({queryKey: leadsKeys.all});
            void queryClient.invalidateQueries({queryKey: customersKeys.all});
            toast.success(
              lead.assignee
                ? t('capture.captured_assigned', {
                    name: lead.customer.name,
                    rep: lead.assignee.name,
                  })
                : t('capture.captured', {name: lead.customer.name}),
            );
            onCaptured(lead);
          }}
        />
      </DialogContent>
    </Dialog>
  );
}
