import {screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {LeadList} from './LeadList';

const listLeads = vi.hoisted(() => vi.fn());
vi.mock('@/entities/lead', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/lead')>()),
  listLeads,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({me: {user: {id: 'u1', role: 'sales'}}}),
}));

const lead = {
  id: 'l1',
  customer: {id: 'c1', name: 'Sarah Johnson'},
  source: 'walk_in',
  status: 'new',
  received_at: new Date().toISOString(),
  created_at: new Date().toISOString(),
  channel_note: null,
  assignee: null,
  vehicle_interest_text: 'RAV4 Hybrid',
  first_contacted_at: null,
  deal_id: null,
};

describe('LeadList', () => {
  beforeEach(() => {
    listLeads.mockReset();
  });

  it('shows skeletons while loading', () => {
    listLeads.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<LeadList />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('lists a lead with its source, status and an unassigned rep', async () => {
    listLeads.mockResolvedValue({
      items: [lead],
      total: 1,
      page: 1,
      per_page: 25,
    });
    renderWithProviders(<LeadList />);

    const [link] = await screen.findAllByRole('link', {name: 'Sarah Johnson'});
    const row = link?.closest('tr');
    expect(row).not.toBeNull();
    const cells = within(row as HTMLElement);
    expect(cells.getByText('Walk-in')).toBeVisible();
    expect(cells.getByText('New')).toBeVisible();
    expect(cells.getByText('Unassigned')).toBeVisible();
    expect(cells.getByText('RAV4 Hybrid')).toBeVisible();
  });

  it('says there are no leads yet and offers the action', async () => {
    listLeads.mockResolvedValue({items: [], total: 0, page: 1, per_page: 25});
    renderWithProviders(
      <LeadList emptyAction={<button>Capture lead</button>} />,
    );
    expect(
      await screen.findByText(
        'No leads yet. Capture the first walk-in or phone call.',
      ),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Capture lead'})).toBeVisible();
  });

  it('filters by the URL and clears every filter from "Show all"', async () => {
    listLeads.mockResolvedValue({items: [], total: 0, page: 1, per_page: 25});
    renderWithProviders(<LeadList />, {route: '/leads?status=closed&q=zzz'});

    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeVisible();
    expect(listLeads).toHaveBeenCalledWith(
      expect.objectContaining({status: 'closed', q: 'zzz'}),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Show all'}));
    expect(listLeads).toHaveBeenLastCalledWith({page: 1, per_page: 25});
  });

  it('shows an error with a retry', async () => {
    listLeads.mockRejectedValue(new Error('down'));
    renderWithProviders(<LeadList />);
    expect(
      await screen.findByText('This list could not be loaded.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Try again'})).toBeVisible();
  });
});
