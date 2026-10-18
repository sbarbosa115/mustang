import {useTranslation} from 'react-i18next';
import {useAuth} from '@/entities/user';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {BookAppointmentForm, type CustomerRef} from './BookAppointmentForm';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Book for this customer; without it the form searches for one. */
  customer?: CustomerRef;
}

/** Book a visit, test drive or delivery for a customer, in the dealership's time. */
export function BookAppointmentDialog({open, onOpenChange, customer}: Props) {
  const {t} = useTranslation('appointments');
  const {me} = useAuth();
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('book_dialog.title')}</DialogTitle>
          <DialogDescription>{t('book_dialog.description')}</DialogDescription>
        </DialogHeader>
        {/* The form is mounted on every open, so it starts empty. */}
        <BookAppointmentForm
          customer={customer}
          defaultUserId={me?.user.id}
          timeZone={me?.dealership.timezone}
          onDone={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  );
}
