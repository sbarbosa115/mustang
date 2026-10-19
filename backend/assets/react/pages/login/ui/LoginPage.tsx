import {zodResolver} from '@hookform/resolvers/zod';
import {useQueryClient} from '@tanstack/react-query';
import {useState, type ReactNode} from 'react';
import {useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {Navigate, useLocation, useNavigate} from 'react-router';
import {z} from 'zod';
import {useAuth, usersKeys} from '@/entities/user';
import {MfaEnrollFlow} from '@/features/mfa-enroll';
import {MfaVerifyForm} from '@/features/mfa-verify';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Card, CardContent, CardDescription, CardHeader} from '@/shared/ui/card';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';

const schema = z.object({
  email: z.string().trim().min(1),
  password: z.string().min(1),
});
type Values = z.infer<typeof schema>;
type Step = 'password' | 'mfa' | 'enroll';

interface LoginState {
  from?: string;
  step?: Step;
}

/**
 * Which step to show. The step travels in the navigation state of /login (it survives a reload); /login/mfa and
 * /login/enroll are read too, for when the router gives them their own path.
 */
function stepOf(pathname: string, state: LoginState | null): Step {
  const fromPath = /\/login\/(mfa|enroll)\/?$/.exec(pathname)?.[1] as
    Step | undefined;
  return fromPath ?? state?.step ?? 'password';
}

function Frame({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle: string;
  children: ReactNode;
}) {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-canvas p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <h1 className="text-2xl leading-none font-semibold">{title}</h1>
          <CardDescription>{subtitle}</CardDescription>
        </CardHeader>
        <CardContent>{children}</CardContent>
      </Card>
    </div>
  );
}

/** Email and password, then the second factor: the code from the app, or enrollment on the first sign-in. */
export function LoginPage() {
  const {t} = useTranslation('auth');
  const {me, signIn, signOut} = useAuth();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();
  const state = location.state as LoginState | null;
  const step = stepOf(location.pathname, state);
  const from = state?.from ?? '/today';

  if (me) return <Navigate to={from} replace />;

  // The session is now verified: load who signed in, and the redirect above takes them where they were going.
  const finish = async () => {
    await queryClient.invalidateQueries({queryKey: [...usersKeys.all, 'me']});
    navigate(from, {replace: true});
  };
  const switchAccount = async () => {
    await signOut();
    navigate('/login', {replace: true, state: {from}});
  };
  const switchLink = (
    <Button
      type="button"
      variant="link"
      className="mt-2 w-full"
      onClick={switchAccount}
    >
      {t('mfa.switch_account')}
    </Button>
  );

  if (step === 'mfa') {
    return (
      <Frame title={t('mfa.verify.title')} subtitle={t('mfa.verify.subtitle')}>
        <MfaVerifyForm onVerified={finish} />
        {switchLink}
      </Frame>
    );
  }
  if (step === 'enroll') {
    return (
      <Frame title={t('mfa.enroll.title')} subtitle={t('mfa.enroll.subtitle')}>
        <MfaEnrollFlow onDone={finish} />
        {switchLink}
      </Frame>
    );
  }
  return (
    <Frame title={t('login.title')} subtitle={t('login.subtitle')}>
      <PasswordForm
        signIn={signIn}
        onNext={(next) =>
          next === 'ok'
            ? navigate(from, {replace: true})
            : navigate('/login', {replace: true, state: {from, step: next}})
        }
      />
    </Frame>
  );
}

function PasswordForm({
  signIn,
  onNext,
}: {
  signIn: ReturnType<typeof useAuth>['signIn'];
  onNext: (next: 'ok' | 'mfa' | 'enroll') => void;
}) {
  const {t} = useTranslation('auth');
  const [error, setError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {email: '', password: ''},
  });

  const onSubmit = form.handleSubmit(async ({email, password}) => {
    setError(null);
    try {
      onNext((await signIn(email, password)).next);
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'generic';
      setError(
        t(
          `login.error.${code === 'too_many_attempts' || code === 'invalid_credentials' ? code : 'generic'}`,
        ),
      );
    }
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <div className="space-y-1.5">
        <Label htmlFor="email">{t('login.email')}</Label>
        <Input
          id="email"
          type="email"
          autoComplete="username"
          autoFocus
          {...form.register('email')}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="password">{t('login.password')}</Label>
        <Input
          id="password"
          type="password"
          autoComplete="current-password"
          {...form.register('password')}
        />
      </div>
      {error && (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      )}
      <Button
        type="submit"
        size="lg"
        className="w-full"
        disabled={!form.formState.isValid || form.formState.isSubmitting}
      >
        {form.formState.isSubmitting
          ? t('login.submitting')
          : t('login.submit')}
      </Button>
    </form>
  );
}
