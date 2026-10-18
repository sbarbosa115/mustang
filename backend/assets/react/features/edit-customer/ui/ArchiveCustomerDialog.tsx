import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
  archiveCustomer,
  customersKeys,
  type Customer,
} from '@/entities/customer';
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
  customer: Customer;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onArchived: () => void;
}

/** Archiving asks first and names the customer (DESIGN.md: destructive actions confirm). */
export function ArchiveCustomerDialog({
  customer,
  open,
  onOpenChange,
  onArchived,
}: Props) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function archive() {
    setBusy(true);
    setError(null);
    try {
      const archived = await archiveCustomer(customer.id);
      queryClient.setQueryData(customersKeys.detail(customer.id), archived);
      void queryClient.invalidateQueries({queryKey: ['customers', 'list']});
      toast.success(t('archive.done', {name: customer.name}));
      onOpenChange(false);
      onArchived();
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
            {t('archive.title', {name: customer.name})}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {t('archive.description')}
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
            onClick={() => void archive()}
          >
            {t('archive.confirm')}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
