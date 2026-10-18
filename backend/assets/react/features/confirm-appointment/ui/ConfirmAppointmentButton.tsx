import {useMutation, useQueryClient} from '@tanstack/react-query';
import {CheckCheck} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {confirmAppointment, type Appointment} from '@/entities/appointment';
import {Button} from '@/shared/ui/button';
import {appointmentChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

/** The rep records that the customer confirmed the visit. */
export function ConfirmAppointmentButton({
  appointment,
}: {
  appointment: Appointment;
}) {
  const {t, i18n} = useTranslation('appointments');
  const queryClient = useQueryClient();
  const confirm = useMutation({
    mutationFn: () => confirmAppointment(appointment.id),
    onSuccess: (updated) => {
      appointmentChanged(queryClient, updated);
      toast.success(t('confirm.done', {name: updated.customer.name}));
    },
    onError: (e) => toast.error(errorText(t, (k) => i18n.exists(k), e)),
  });
  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      disabled={confirm.isPending}
      aria-label={t('card.confirm_for', {name: appointment.customer.name})}
      onClick={() => confirm.mutate()}
    >
      <CheckCheck aria-hidden />
      {t('card.confirm')}
    </Button>
  );
}
