import {zodResolver} from '@hookform/resolvers/zod';
import {useState} from 'react';
import {useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {Navigate, useLocation, useNavigate} from 'react-router';
import {z} from 'zod';
import {useAuth} from '@/entities/user';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/ui/card';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';

const schema = z.object({
  email: z.string().trim().min(1),
  password: z.string().min(1),
});
type Values = z.infer<typeof schema>;

/** Email and password. The MFA step (item "mfa") follows when the API answers next = mfa or enroll. */
export function LoginPage() {
  const {t} = useTranslation('auth');
  const {me, signIn} = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [error, setError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {email: '', password: ''},
  });
  const from = (location.state as {from?: string} | null)?.from ?? '/today';

  if (me) return <Navigate to={from} replace />;

  const onSubmit = form.handleSubmit(async ({email, password}) => {
    setError(null);
    try {
      const result = await signIn(email, password);
      if (result.next === 'ok') navigate(from, {replace: true});
      else navigate(`/login/${result.next}`, {replace: true, state: {from}});
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'generic';
      setError(
        t(
          `login.error.${code === 'too_many_attempts' ? code : code === 'invalid_credentials' ? code : 'generic'}`,
        ),
      );
    }
  });

  return (
    <div className="flex min-h-dvh items-center justify-center bg-canvas p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-2xl">{t('login.title')}</CardTitle>
          <CardDescription>{t('login.subtitle')}</CardDescription>
        </CardHeader>
        <CardContent>
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
        </CardContent>
      </Card>
    </div>
  );
}
