import {screen, within} from '@testing-library/react';
import {describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {ThemeProvider} from '@/shared/lib/theme';
import {BottomTabs} from './BottomTabs';
import {NavLinks} from './NavLinks';

const auth = vi.hoisted(() => ({roles: ['sales'] as string[]}));
vi.mock('@/entities/user', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/entities/user')>();
  return {
    ...actual,
    useAuth: () => ({
      me: {
        user: {name: 'Sam Patel'},
        dealership: {name: 'Mustang Motors Tampa'},
        roles: auth.roles,
      },
      isLoading: false,
      signIn: vi.fn(),
      signOut: vi.fn(),
    }),
  };
});

describe('navigation', () => {
  it('shows a rep no manager screens', () => {
    auth.roles = ['sales'];
    renderWithProviders(<NavLinks />);
    expect(screen.getByRole('link', {name: 'Today'})).toBeInTheDocument();
    expect(
      screen.queryByRole('link', {name: 'Dashboard'}),
    ).not.toBeInTheDocument();
    expect(screen.queryByRole('link', {name: 'Team'})).not.toBeInTheDocument();
  });

  it('shows a manager the dashboard and the team', () => {
    auth.roles = ['manager', 'sales', 'bdc'];
    renderWithProviders(<NavLinks />);
    expect(screen.getByRole('link', {name: 'Dashboard'})).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Team'})).toBeInTheDocument();
  });

  it('puts Today, Inbox, Customers, Inventory and More in the phone tab bar', () => {
    auth.roles = ['sales'];
    renderWithProviders(
      <ThemeProvider>
        <BottomTabs />
      </ThemeProvider>,
    );
    const bar = screen.getByRole('navigation', {name: 'Main'});
    expect(
      within(bar)
        .getAllByRole('link')
        .map((link) => link.textContent),
    ).toEqual(['Today', 'Inbox', 'Customers', 'Inventory']);
    expect(within(bar).getByRole('button', {name: 'More'})).toBeInTheDocument();
  });
});
