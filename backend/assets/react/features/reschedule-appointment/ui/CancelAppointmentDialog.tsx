import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {cancelAppointment, type Appointment} from '@/entities/appointment';
import {useAuth} from '@/entities/user';
import {formatDateTime} from '@/shared/lib/dates';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import {appointmentChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

interface Props {
  appointment: Appointment;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Cancelling confirms first and names the appointment (DESIGN.md: destructive actions). */
export function CancelAppointmentDialog({
  appointment,
  open,
  onOpenChange,
}: Props) {
  const {t, i18n} = useTranslation('appointments');
  const {me} = useAuth();
  const queryClient = useQueryClient();
  const cancel = useMutation({
    mutationFn: () => cancelAppointment(appointment.id),
    onSuccess: (updated) => {
      appointmentChanged(queryClient, updated);
      toast.success(t('cancel_dialog.done', {name: updated.customer.name}));
      onOpenChange(false);
    },
    onError: (e) => toast.error(errorText(t, (k) => i18n.exists(k), e)),
  });
  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            {t('cancel_dialog.title', {name: appointment.customer.name})}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {t('cancel_dialog.description', {
              when: formatDateTime(
                appointment.scheduled_at,
                me?.dealership.timezone,
              ),
            })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{t('cancel_dialog.keep')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={cancel.isPending}
            onClick={(event) => {
              // Stay open until the server answers, so a refusal can be read.
              event.preventDefault();
              cancel.mutate();
            }}
          >
            {t('cancel_dialog.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
