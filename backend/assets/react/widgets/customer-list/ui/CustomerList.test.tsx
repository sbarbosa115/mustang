import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {CustomerList} from './CustomerList';

const listCustomers = vi.hoisted(() => vi.fn());
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  listCustomers,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({me: {user: {id: 'u1', role: 'sales'}}}),
}));

const row = (id: string, name: string, extra = {}) => ({
  id,
  name,
  first_name: name.split(' ')[0],
  last_name: name.split(' ')[1],
  archived: false,
  updated_at: '2026-10-18T12:00:00+00:00',
  primary_phone: '+18135551001',
  primary_email: `${id}@example.com`,
  sales1: {id: 'u1', name: 'Jordan Reyes'},
  open_deal_stage: null,
  ...extra,
});
const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});

describe('CustomerList', () => {
  beforeEach(() => {
    listCustomers.mockReset();
  });

  it('shows skeletons while the first page loads', () => {
    listCustomers.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<CustomerList />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('lists customers with their phone, rep and deal stage', async () => {
    listCustomers.mockResolvedValue(
      page([
        row('c1', 'Sarah Johnson', {open_deal_stage: 'engaged'}),
        row('c2', 'Michael Williams'),
      ]),
    );
    renderWithProviders(<CustomerList />);

    expect(
      (await screen.findAllByRole('link', {name: 'Sarah Johnson'})).length,
    ).toBeGreaterThan(0);
    expect(screen.getAllByText('(813) 555-1001').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Engaged').length).toBeGreaterThan(0);
    expect(screen.getAllByText('No open deal').length).toBeGreaterThan(0);
  });

  it('says there are no customers yet and offers the one action that fills it', async () => {
    listCustomers.mockResolvedValue(page([]));
    renderWithProviders(
      <CustomerList emptyAction={<button>Add the first</button>} />,
    );

    expect(
      await screen.findByText('No customers yet. Add the first one.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Add the first'})).toBeVisible();
  });

  it('says nothing matches a search and Show all brings the list back', async () => {
    listCustomers.mockImplementation((query: {q?: string}) =>
      Promise.resolve(query.q ? page([]) : page([row('c1', 'Sarah Johnson')])),
    );
    renderWithProviders(<CustomerList />);
    await screen.findAllByRole('link', {name: 'Sarah Johnson'});

    await userEvent.type(screen.getByLabelText('Search customers'), 'zzz');
    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeVisible();
    expect(listCustomers).toHaveBeenLastCalledWith(
      expect.objectContaining({q: 'zzz'}),
    );

    await userEvent.click(screen.getByRole('button', {name: 'Show all'}));
    expect(
      (await screen.findAllByRole('link', {name: 'Sarah Johnson'})).length,
    ).toBeGreaterThan(0);
    expect(screen.getByLabelText('Search customers')).toHaveValue('');
  });

  it('offers a retry when the list cannot be loaded', async () => {
    listCustomers.mockImplementationOnce(() =>
      Promise.reject(new Error('down')),
    );
    listCustomers.mockResolvedValue(page([row('c1', 'Sarah Johnson')]));
    renderWithProviders(<CustomerList />);

    await userEvent.click(
      await screen.findByRole('button', {name: 'Try again'}),
    );
    await waitFor(() =>
      expect(screen.getAllByText('Sarah Johnson').length).toBeGreaterThan(0),
    );
  });
});
