import {useMutation} from '@tanstack/react-query';
import {useState, type FormEvent} from 'react';
import {useTranslation} from 'react-i18next';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {verifyMfa, type MfaStatus} from '../api';
import {mfaErrorKey} from './mfaError';

type Mode = 'code' | 'recovery';

/**
 * The code step of sign-in: six digits from the authenticator app, or one of the recovery codes when the phone is
 * not at hand. Refusals are shown by their code (wrong, already used, too many attempts).
 */
export function MfaVerifyForm({
  onVerified,
}: {
  onVerified: (status: MfaStatus) => void;
}) {
  const {t} = useTranslation('auth');
  const [mode, setMode] = useState<Mode>('code');
  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const verify = useMutation({
    mutationFn: (body: Parameters<typeof verifyMfa>[0]) => verifyMfa(body),
    onSuccess: (status) => onVerified(status),
  });

  const digits = code.replace(/\D/g, '');
  const ready =
    mode === 'code' ? digits.length === 6 : recoveryCode.trim() !== '';

  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    if (!ready || verify.isPending) return;
    verify.mutate(
      mode === 'code' ? {code: digits} : {recovery_code: recoveryCode.trim()},
    );
  };

  const switchMode = () => {
    verify.reset();
    setMode(mode === 'code' ? 'recovery' : 'code');
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      {mode === 'code' ? (
        <div className="space-y-1.5">
          <Label htmlFor="mfa-code">{t('mfa.verify.code')}</Label>
          <Input
            id="mfa-code"
            inputMode="numeric"
            autoComplete="one-time-code"
            autoFocus
            maxLength={7}
            className="font-mono tracking-widest"
            value={code}
            onChange={(e) => setCode(e.target.value)}
          />
        </div>
      ) : (
        <div className="space-y-1.5">
          <Label htmlFor="mfa-recovery">{t('mfa.verify.recovery_code')}</Label>
          <Input
            id="mfa-recovery"
            autoComplete="off"
            autoCapitalize="characters"
            spellCheck={false}
            autoFocus
            className="font-mono"
            aria-describedby="mfa-recovery-help"
            value={recoveryCode}
            onChange={(e) => setRecoveryCode(e.target.value)}
          />
          <p id="mfa-recovery-help" className="text-sm text-muted-foreground">
            {t('mfa.verify.recovery_help')}
          </p>
        </div>
      )}
      {verify.isError && (
        <p role="alert" className="text-sm text-danger">
          {t(mfaErrorKey(verify.error))}
        </p>
      )}
      <Button
        type="submit"
        size="lg"
        className="w-full"
        disabled={!ready || verify.isPending}
      >
        {verify.isPending ? t('mfa.verify.submitting') : t('mfa.verify.submit')}
      </Button>
      <Button
        type="button"
        variant="link"
        className="w-full"
        onClick={switchMode}
      >
        {mode === 'code'
          ? t('mfa.verify.use_recovery')
          : t('mfa.verify.use_app')}
      </Button>
    </form>
  );
}
