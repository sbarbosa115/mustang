import {useMutation, useQueryClient} from '@tanstack/react-query';
import {UserX} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {markNoShow, type Appointment} from '@/entities/appointment';
import {Button} from '@/shared/ui/button';
import {appointmentChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

/** The customer did not come. The no-show shows up in the worklist and the customer's timeline. */
export function MarkNoShowButton({appointment}: {appointment: Appointment}) {
  const {t, i18n} = useTranslation('appointments');
  const queryClient = useQueryClient();
  const missed = useMutation({
    mutationFn: () => markNoShow(appointment.id),
    onSuccess: (updated) => {
      appointmentChanged(queryClient, updated);
      toast.success(t('no_show.done', {name: updated.customer.name}));
    },
    onError: (e) => toast.error(errorText(t, (k) => i18n.exists(k), e)),
  });
  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      disabled={missed.isPending}
      aria-label={t('card.no_show_for', {name: appointment.customer.name})}
      onClick={() => missed.mutate()}
    >
      <UserX aria-hidden />
      {t('card.no_show')}
    </Button>
  );
}
