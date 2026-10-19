import {useMutation, useQueryClient} from '@tanstack/react-query';
import {UserCheck} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {markShown, type Appointment} from '@/entities/appointment';
import {Button} from '@/shared/ui/button';
import {appointmentChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

/** The customer came: the appointment is shown and the deal moves to visit. */
export function MarkShownButton({appointment}: {appointment: Appointment}) {
  const {t, i18n} = useTranslation('appointments');
  const queryClient = useQueryClient();
  const shown = useMutation({
    mutationFn: () => markShown(appointment.id),
    onSuccess: (updated) => {
      appointmentChanged(queryClient, updated);
      toast.success(t('shown.done', {name: updated.customer.name}));
    },
    onError: (e) => toast.error(errorText(t, (k) => i18n.exists(k), e)),
  });
  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      disabled={shown.isPending}
      aria-label={t('card.shown_for', {name: appointment.customer.name})}
      onClick={() => shown.mutate()}
    >
      <UserCheck aria-hidden />
      {t('card.shown')}
    </Button>
  );
}
