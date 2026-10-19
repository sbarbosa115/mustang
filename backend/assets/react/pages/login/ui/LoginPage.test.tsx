import {screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {LoginPage} from './LoginPage';

const auth = vi.hoisted(() => ({signIn: vi.fn(), signOut: vi.fn()}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({me: null, isLoading: false, ...auth}),
}));
vi.mock('@/features/mfa-enroll', () => ({
  MfaEnrollFlow: () => <p>enroll flow</p>,
}));

async function signInWithPassword() {
  await userEvent.type(screen.getByLabelText('Email'), 'sales@mustang.test');
  await userEvent.type(screen.getByLabelText('Password'), 'Mustang-dev-1');
  await userEvent.click(screen.getByRole('button', {name: 'Sign in'}));
}

describe('LoginPage', () => {
  it('asks for the code from the app after the password', async () => {
    auth.signIn.mockResolvedValue({next: 'mfa'});
    renderWithProviders(<LoginPage />, {route: '/login'});

    await signInWithPassword();

    expect(
      await screen.findByRole('heading', {name: 'Enter your code'}),
    ).toBeInTheDocument();
    expect(
      screen.getByLabelText('Code from your authenticator app'),
    ).toBeInTheDocument();
  });

  it('sends a user without a factor to enrollment', async () => {
    auth.signIn.mockResolvedValue({next: 'enroll'});
    renderWithProviders(<LoginPage />, {route: '/login'});

    await signInWithPassword();

    expect(
      await screen.findByRole('heading', {name: 'Set up two-step sign-in'}),
    ).toBeInTheDocument();
    expect(screen.getByText('enroll flow')).toBeInTheDocument();
  });

  it('lets someone else sign in from the code step', async () => {
    auth.signIn.mockResolvedValue({next: 'mfa'});
    auth.signOut.mockResolvedValue(undefined);
    renderWithProviders(<LoginPage />, {route: '/login'});
    await signInWithPassword();

    await userEvent.click(
      await screen.findByRole('button', {name: 'Sign in as someone else'}),
    );

    expect(auth.signOut).toHaveBeenCalled();
    expect(
      await screen.findByRole('heading', {name: 'Sign in to Mustang'}),
    ).toBeInTheDocument();
  });
});
