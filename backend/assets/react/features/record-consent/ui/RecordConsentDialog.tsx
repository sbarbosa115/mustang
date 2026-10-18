import {useTranslation} from 'react-i18next';
import type {Customer} from '@/entities/customer';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {ConsentForm} from './ConsentForm';

interface Props {
  customer: Customer;
  timeZone?: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** "Record consent" as a dialog over the customer's consent page. */
export function RecordConsentDialog({
  customer,
  timeZone,
  open,
  onOpenChange,
}: Props) {
  const {t} = useTranslation('consent');
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('record.title')}</DialogTitle>
          <DialogDescription>{t('record.description')}</DialogDescription>
        </DialogHeader>
        {open && (
          <ConsentForm
            customer={customer}
            timeZone={timeZone}
            onDone={() => onOpenChange(false)}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}
