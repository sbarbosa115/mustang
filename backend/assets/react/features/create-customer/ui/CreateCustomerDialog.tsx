import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {customersKeys, type Customer} from '@/entities/customer';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {CustomerForm} from './CustomerForm';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: (customer: Customer) => void;
}

/** "Add customer" as a dialog: the top bar, the customer list and the empty state all open this one. */
export function CreateCustomerDialog({open, onOpenChange, onCreated}: Props) {
  const {t} = useTranslation('customers');
  const queryClient = useQueryClient();
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('create.title')}</DialogTitle>
          <DialogDescription>{t('create.description')}</DialogDescription>
        </DialogHeader>
        <CustomerForm
          onCancel={() => onOpenChange(false)}
          onCreated={(customer) => {
            void queryClient.invalidateQueries({queryKey: customersKeys.all});
            toast.success(t('create.created', {name: customer.name}));
            onCreated(customer);
          }}
        />
      </DialogContent>
    </Dialog>
  );
}
