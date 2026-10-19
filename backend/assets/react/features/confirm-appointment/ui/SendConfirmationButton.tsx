import {useMutation} from '@tanstack/react-query';
import {MessageSquareText} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {toast} from 'sonner';
import type {Appointment} from '@/entities/appointment';
import {fetchCustomerConversation} from '@/entities/conversation';
import {Button} from '@/shared/ui/button';
import {errorText} from '../lib/errors';

/** The template the inbox composer opens with (item "templates" seeds it under this name). */
export const CONFIRMATION_TEMPLATE = 'appointment-confirmation';

/**
 * Opens the customer's conversation in the inbox with the confirmation template ready to send. Whether the customer
 * can be texted is the composer's business: it shows consent before anything is sent.
 */
export function SendConfirmationButton({
  appointment,
}: {
  appointment: Appointment;
}) {
  const {t, i18n} = useTranslation('appointments');
  const navigate = useNavigate();
  const open = useMutation({
    mutationFn: () => fetchCustomerConversation(appointment.customer.id),
    onSuccess: (conversation) =>
      void navigate(
        `/inbox/${conversation.conversation.id}?template=${CONFIRMATION_TEMPLATE}&appointment=${appointment.id}`,
      ),
    onError: (e) => toast.error(errorText(t, (k) => i18n.exists(k), e)),
  });
  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      disabled={open.isPending}
      onClick={() => open.mutate()}
    >
      <MessageSquareText aria-hidden />
      {t('card.send_confirmation')}
    </Button>
  );
}
