import {useMutation, useQuery} from '@tanstack/react-query';
import {Copy} from 'lucide-react';
import {useState, type FormEvent} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {Button} from '@/shared/ui/button';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {Skeleton} from '@/shared/ui/skeleton';
import {confirmMfa, enrollMfa} from '../api';
import {mfaErrorKey} from './mfaError';

/** The key in groups of four, easier to type into an app. */
function grouped(secret: string): string {
  return secret.match(/.{1,4}/g)?.join(' ') ?? secret;
}

/**
 * First sign-in: scan the QR code (or type the key), prove it with a code, then save the recovery codes, which are
 * shown this once. `onDone` runs when the user says they saved them.
 */
export function MfaEnrollFlow({onDone}: {onDone: () => void}) {
  const {t} = useTranslation('auth');
  const [code, setCode] = useState('');
  // A query, not a mutation: it runs once per screen, even when React mounts it twice, so the QR code shown is the
  // secret the server keeps.
  const enrollment = useQuery({
    queryKey: ['mfa', 'enrollment'],
    queryFn: enrollMfa,
    staleTime: Infinity,
    gcTime: 0,
    retry: false,
    refetchOnWindowFocus: false,
  });
  const confirm = useMutation({
    mutationFn: (value: string) => confirmMfa(value),
  });

  if (confirm.data) {
    const codes = confirm.data;
    const copy = async () => {
      try {
        await navigator.clipboard.writeText(codes.join('\n'));
        toast.success(t('mfa.codes.copied'));
      } catch {
        // The codes stay on screen to be written down.
      }
    };
    return (
      <div className="space-y-4">
        <div className="space-y-1">
          <h2 className="font-semibold">{t('mfa.codes.title')}</h2>
          <p className="text-sm text-muted-foreground">
            {t('mfa.codes.subtitle')}
          </p>
        </div>
        <ol
          aria-label={t('mfa.codes.list')}
          className="grid grid-cols-2 gap-2 rounded-md border bg-muted p-3 font-mono text-sm"
        >
          {codes.map((recovery) => (
            <li key={recovery}>{recovery}</li>
          ))}
        </ol>
        <Button
          type="button"
          variant="outline"
          className="w-full"
          onClick={copy}
        >
          <Copy aria-hidden />
          {t('mfa.codes.copy')}
        </Button>
        <Button type="button" size="lg" className="w-full" onClick={onDone}>
          {t('mfa.codes.done')}
        </Button>
      </div>
    );
  }

  if (enrollment.isPending) {
    return (
      <div
        className="space-y-3"
        aria-busy="true"
        aria-label={t('mfa.enroll.loading')}
      >
        <Skeleton className="mx-auto size-48" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-11 w-full" />
      </div>
    );
  }

  if (enrollment.isError) {
    return (
      <div className="space-y-3">
        <p role="alert" className="text-sm text-danger">
          {enrollment.error instanceof Error &&
          mfaErrorKey(enrollment.error) !== 'mfa.error.generic'
            ? t(mfaErrorKey(enrollment.error))
            : t('mfa.enroll.load_error')}
        </p>
      </div>
    );
  }

  const {secret, qr_svg: qrSvg} = enrollment.data;
  const digits = code.replace(/\D/g, '');
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    if (digits.length === 6 && !confirm.isPending) confirm.mutate(digits);
  };

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <p className="text-sm">{t('mfa.enroll.step_scan')}</p>
      <img
        src={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrSvg)}`}
        alt={t('mfa.enroll.qr_alt')}
        width={192}
        height={192}
        className="mx-auto size-48 rounded-md bg-white p-2"
      />
      <div className="space-y-1">
        <p className="text-sm text-muted-foreground">
          {t('mfa.enroll.manual')}
        </p>
        <p className="font-mono text-sm break-all select-all">
          {grouped(secret)}
        </p>
      </div>
      <div className="space-y-1.5">
        <p className="text-sm">{t('mfa.enroll.step_code')}</p>
        <Label htmlFor="mfa-enroll-code">{t('mfa.verify.code')}</Label>
        <Input
          id="mfa-enroll-code"
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={7}
          className="font-mono tracking-widest"
          value={code}
          onChange={(e) => setCode(e.target.value)}
        />
      </div>
      {confirm.isError && (
        <p role="alert" className="text-sm text-danger">
          {t(mfaErrorKey(confirm.error))}
        </p>
      )}
      <Button
        type="submit"
        size="lg"
        className="w-full"
        disabled={digits.length !== 6 || confirm.isPending}
      >
        {confirm.isPending
          ? t('mfa.enroll.submitting')
          : t('mfa.enroll.submit')}
      </Button>
    </form>
  );
}
