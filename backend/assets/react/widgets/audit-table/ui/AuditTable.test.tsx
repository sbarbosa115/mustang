import {fireEvent, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {AuditTable} from './AuditTable';

const listAuditEntries = vi.hoisted(() => vi.fn());
const listUsers = vi.hoisted(() => vi.fn());
vi.mock('@/entities/audit-entry', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/audit-entry')>()),
  listAuditEntries,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  listUsers,
  useAuth: () => ({
    me: {
      user: {id: 'u1', role: 'admin'},
      dealership: {timezone: 'America/New_York'},
    },
  }),
}));

const entry = (id: string, extra = {}) => ({
  id,
  action: 'update',
  entity_type: 'Customer',
  entity_id: 'c1000000-0000-7000-8000-000000000001',
  occurred_at: new Date().toISOString(),
  actor: {id: 'u2', name: 'Brianna Diaz'},
  customer_id: 'c1000000-0000-7000-8000-000000000001',
  changes: {last_name: {old: 'Garcia', new: 'Audited'}},
  ip: '10.0.0.7',
  ...extra,
});
const page = (items: unknown[], total = items.length) => ({
  items,
  total,
  page: 1,
  per_page: 25,
});

describe('AuditTable', () => {
  beforeEach(() => {
    listAuditEntries.mockReset();
    listUsers.mockReset();
    listUsers.mockResolvedValue({items: [], total: 0, page: 1, per_page: 100});
  });

  it('shows skeletons while the first page loads', () => {
    listAuditEntries.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<AuditTable />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('lists who did what to which record, with the field diff', async () => {
    listAuditEntries.mockResolvedValue(
      page([
        entry('a1'),
        entry('a2', {
          action: 'read',
          changes: null,
          actor: {id: 'u3', name: 'Sam Patel'},
        }),
        entry('a3', {
          action: 'create',
          entity_type: 'Suppression',
          entity_id: 'abcdef12-0000',
          customer_id: null,
          actor: null,
          ip: null,
          changes: {contact_value: {old: null, new: '+18135551001'}},
        }),
      ]),
    );
    renderWithProviders(<AuditTable />);

    expect((await screen.findAllByText('Brianna Diaz')).length).toBeGreaterThan(
      0,
    );
    expect(screen.getAllByText('Changed').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Viewed').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Created').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Garcia').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Audited').length).toBeGreaterThan(0);
    expect(screen.getAllByText('From 10.0.0.7').length).toBeGreaterThan(0);
    expect(screen.getAllByText('System').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Opt-out').length).toBeGreaterThan(0);
    expect(
      screen.getAllByRole('link', {name: 'Open the customer'})[0],
    ).toHaveAttribute(
      'href',
      '/customers/c1000000-0000-7000-8000-000000000001',
    );
  });

  it('never shows a masked value, only that it changed', async () => {
    listAuditEntries.mockResolvedValue(
      page([
        entry('a1', {
          entity_type: 'User',
          customer_id: null,
          changes: {password_hash: {old: '[masked]', new: '[masked]'}},
        }),
      ]),
    );
    renderWithProviders(<AuditTable />);

    expect(
      (await screen.findAllByText('password_hash')).length,
    ).toBeGreaterThan(0);
    expect(screen.getAllByText('hidden').length).toBeGreaterThan(0);
    expect(screen.queryByText('[masked]')).toBeNull();
  });

  it('shows the first fields of a long diff and the rest on request', async () => {
    listAuditEntries.mockResolvedValue(
      page([
        entry('a1', {
          action: 'create',
          changes: {
            first_name: {old: null, new: 'Ana'},
            last_name: {old: null, new: 'Ruiz'},
            source: {old: null, new: 'manual'},
            preferred_channel: {old: null, new: 'text'},
            bdc_user_id: {old: null, new: null},
          },
        }),
      ]),
    );
    renderWithProviders(<AuditTable />);

    const more = (
      await screen.findAllByRole('button', {name: 'Show 2 more fields'})
    )[0]!;
    expect(screen.queryAllByText('preferred_channel')).toHaveLength(0);
    await userEvent.click(more);
    expect(screen.getAllByText('preferred_channel').length).toBeGreaterThan(0);
  });

  it('narrows the log to one record from its row, and asks the API for it', async () => {
    listAuditEntries.mockResolvedValue(page([entry('a1')]));
    renderWithProviders(<AuditTable />);

    await userEvent.click(
      (
        await screen.findAllByRole('button', {
          name: "Show this record's history",
        })
      )[0]!,
    );

    await waitFor(() =>
      expect(listAuditEntries).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 25,
        entity_type: 'Customer',
        entity_id: 'c1000000-0000-7000-8000-000000000001',
      }),
    );
    expect(screen.getByText('History of Customer c1000000')).toBeVisible();
  });

  it('sends the period with the last day included', async () => {
    listAuditEntries.mockResolvedValue(page([entry('a1')]));
    renderWithProviders(<AuditTable />);
    await screen.findAllByText('Brianna Diaz');

    fireEvent.change(screen.getByLabelText('From'), {
      target: {value: '2026-10-01'},
    });
    fireEvent.change(screen.getByLabelText('To'), {
      target: {value: '2026-10-31'},
    });

    await waitFor(() =>
      expect(listAuditEntries).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 25,
        from: '2026-10-01',
        to: '2026-11-01',
      }),
    );
  });

  it('says so when nothing was recorded yet', async () => {
    listAuditEntries.mockResolvedValue(page([]));
    renderWithProviders(<AuditTable />);
    expect(
      await screen.findByText('Nothing has been recorded yet.'),
    ).toBeVisible();
  });

  it('offers to clear filters that match nothing', async () => {
    listAuditEntries.mockResolvedValue(page([]));
    renderWithProviders(<AuditTable />, {
      route: '/settings/audit?entity_type=Deal&user_id=u9',
    });

    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeVisible();
    expect(listAuditEntries).toHaveBeenCalledWith({
      page: 1,
      per_page: 25,
      entity_type: 'Deal',
      user_id: 'u9',
    });
    await userEvent.click(screen.getByRole('button', {name: 'Show all'}));
    await waitFor(() =>
      expect(listAuditEntries).toHaveBeenLastCalledWith({
        page: 1,
        per_page: 25,
      }),
    );
  });

  it('shows an error with a retry', async () => {
    listAuditEntries.mockRejectedValue(new Error('boom'));
    renderWithProviders(<AuditTable />);
    const error = await screen.findByText('This list could not be loaded.');
    expect(error).toBeVisible();
    expect(
      within(document.body).getByRole('button', {name: 'Try again'}),
    ).toBeVisible();
  });
});
