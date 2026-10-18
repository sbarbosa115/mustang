import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {closeLead, leadsKeys, type Lead} from '@/entities/lead';
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
  lead: Lead;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Closing a lead asks first and names the customer (DESIGN.md: destructive actions confirm). */
export function CloseLeadDialog({lead, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('leads');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function close() {
    setBusy(true);
    setError(null);
    try {
      const closed = await closeLead(lead.id, {reason: null});
      queryClient.setQueryData(leadsKeys.detail(lead.id), closed);
      void queryClient.invalidateQueries({queryKey: ['leads', 'list']});
      toast.success(t('close.closed'));
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
            {t('close.title', {name: lead.customer.name})}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {t('close.description')}
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
            onClick={() => void close()}
          >
            {t('close.confirm')}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
