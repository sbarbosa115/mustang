import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {consentsKeys, revokeConsent, type Consent} from '@/entities/consent';
import {customersKeys} from '@/entities/customer';
import {ApiError} from '@/shared/api';
import {formatPhone} from '@/shared/lib/phone';
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
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';

interface Props {
  consent: Consent | null;
  onOpenChange: (open: boolean) => void;
}

/** Revoking asks first and names the channel and contact (DESIGN.md: destructive actions confirm). */
export function RevokeConsentDialog({consent, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('consent');
  const queryClient = useQueryClient();
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const names = consent
    ? {
        channel: t(`common:channel.${consent.channel}`),
        contact:
          consent.channel === 'email'
            ? consent.contact_value
            : formatPhone(consent.contact_value),
      }
    : {channel: '', contact: ''};

  const close = (open: boolean) => {
    if (!open) {
      setReason('');
      setError(null);
    }
    onOpenChange(open);
  };

  async function revoke() {
    if (!consent) return;
    setBusy(true);
    setError(null);
    try {
      await revokeConsent(consent.id, {reason: reason.trim() || null});
      void queryClient.invalidateQueries({queryKey: consentsKeys.all});
      void queryClient.invalidateQueries({
        queryKey: customersKeys.detail(consent.customer_id),
      });
      toast.success(t('revoke.done', names));
      close(false);
    } catch (e) {
      const code = e instanceof ApiError ? e.code : '';
      setError(
        code && i18n.exists(`consent:error.${code}`)
          ? t(`error.${code}`)
          : t('common:error.generic'),
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <AlertDialog open={consent !== null} onOpenChange={close}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('revoke.title', names)}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('revoke.description')}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="revoke_reason">{t('revoke.reason')}</Label>
          <Input
            id="revoke_reason"
            value={reason}
            maxLength={255}
            placeholder={t('revoke.reason_placeholder')}
            onChange={(event) => setReason(event.target.value)}
          />
        </div>
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
            onClick={() => void revoke()}
          >
            {t('revoke.confirm')}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
