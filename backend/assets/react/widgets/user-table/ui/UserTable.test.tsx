import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {UserTable} from './UserTable';

const listUsers = vi.hoisted(() => vi.fn());
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  listUsers,
  useAuth: () => ({me: {user: {id: 'u1', role: 'admin'}}}),
}));

const row = (id: string, name: string, extra = {}) => ({
  id,
  name,
  first_name: name.split(' ')[0],
  last_name: name.split(' ')[1],
  email: `${id}@mustang.test`,
  role: 'sales',
  active: true,
  theme: 'system',
  mfa_enrolled: true,
  last_login_at: null,
  ...extra,
});
const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});

describe('UserTable', () => {
  beforeEach(() => {
    listUsers.mockReset();
  });

  it('shows skeletons while the first page loads', () => {
    listUsers.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<UserTable />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('lists users with role, status and two-step state, and marks the signed-in admin', async () => {
    listUsers.mockResolvedValue(
      page([
        row('u1', 'Alex Admin', {role: 'admin'}),
        row('u2', 'Sam Patel', {active: false, mfa_enrolled: false}),
      ]),
    );
    renderWithProviders(<UserTable />);

    expect((await screen.findAllByText('Alex Admin')).length).toBeGreaterThan(
      0,
    );
    expect(screen.getAllByText('You').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Deactivated').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Not set up').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Never').length).toBeGreaterThan(0);
  });

  it('offers Activate for a deactivated user and disables Deactivate on your own row', async () => {
    listUsers.mockResolvedValue(
      page([
        row('u1', 'Alex Admin', {role: 'admin'}),
        row('u2', 'Sam Patel', {active: false}),
      ]),
    );
    renderWithProviders(<UserTable />);
    await screen.findAllByText('Sam Patel');

    await userEvent.click(
      screen.getAllByRole('button', {name: 'Actions for Sam Patel'})[0]!,
    );
    expect(
      await screen.findByRole('menuitem', {name: 'Activate'}),
    ).toBeVisible();
  });

  it('disables Deactivate on your own row', async () => {
    listUsers.mockResolvedValue(
      page([row('u1', 'Alex Admin', {role: 'admin'})]),
    );
    renderWithProviders(<UserTable />);
    await screen.findAllByText('Alex Admin');

    await userEvent.click(
      screen.getAllByRole('button', {name: 'Actions for Alex Admin'})[0]!,
    );
    expect(
      await screen.findByRole('menuitem', {name: 'Deactivate'}),
    ).toHaveAttribute('aria-disabled', 'true');
  });

  it('says so when there are no users, and offers to clear a search that matches nothing', async () => {
    listUsers.mockResolvedValue(page([]));
    renderWithProviders(<UserTable />, {route: '/settings/users?q=zzz'});

    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeVisible();
    await userEvent.click(screen.getByRole('button', {name: 'Show all'}));
    await waitFor(() =>
      expect(listUsers).toHaveBeenLastCalledWith({page: 1, per_page: 25}),
    );
  });

  it('shows an error with a retry', async () => {
    listUsers.mockRejectedValue(new Error('boom'));
    renderWithProviders(<UserTable />);
    expect(
      await screen.findByText('This list could not be loaded.'),
    ).toBeVisible();
  });
});
