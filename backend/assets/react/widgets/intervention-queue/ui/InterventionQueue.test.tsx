import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {InterventionQueue} from './InterventionQueue';

const fetchInterventions = vi.hoisted(() => vi.fn());
const assignDeal = vi.hoisted(() => vi.fn());
vi.mock('@/entities/intervention', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/intervention')>()),
  fetchInterventions,
}));
vi.mock('@/entities/deal', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/deal')>()),
  assignDeal,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({
    me: {
      user: {id: 'm1', name: 'Morgan Manager', role: 'manager'},
      dealership: {name: 'Mustang Motors', timezone: 'America/New_York'},
      roles: ['manager', 'sales', 'bdc'],
    },
  }),
}));

const deal = (id: string, stage = 'engaged') => ({
  id,
  stage,
  days_in_stage: 2,
  vehicle_text: null,
});
const queue = {
  items: [
    {
      deal: deal('d1'),
      customer: {id: 'c1', name: 'Sarah Johnson'},
      reason_code: 'customer_unanswered',
      reason_params: {minutes: 90},
      at_risk_cents: 450000,
      assignee: {id: 'u1', name: 'Sam Patel'},
      since: null,
    },
    {
      deal: deal('d2', 'new'),
      customer: {id: 'c2', name: 'Marcus Lee'},
      reason_code: 'lead_unattended',
      reason_params: {minutes: 180, unassigned: true},
      at_risk_cents: 300000,
      assignee: null,
      since: null,
    },
    {
      deal: deal('d3', 'appointment'),
      customer: {id: 'c3', name: 'Ana Ruiz'},
      reason_code: 'no_show_without_follow_up',
      reason_params: {hours: 30, scheduled_at: '2026-10-19T12:00:00+00:00'},
      at_risk_cents: 300000,
      assignee: {id: 'm1', name: 'Morgan Manager'},
      since: null,
    },
    {
      deal: deal('d4'),
      customer: {id: 'c4', name: 'Tom Baker'},
      reason_code: 'deal_idle',
      reason_params: {days: 7, stage: 'engaged', threshold: 6},
      at_risk_cents: 300000,
      assignee: {id: 'u2', name: 'Jordan Reyes'},
      since: null,
    },
  ],
  summary: {
    count: 4,
    at_risk_cents: 1350000,
    reps_with_unanswered: [
      {user: {id: 'u2', name: 'Jordan Reyes'}, count: 2},
      {user: {id: 'u1', name: 'Sam Patel'}, count: 1},
    ],
  },
};

/** The n-th row; fails the test when there are fewer. */
function rowAt(rows: HTMLElement[], n: number): HTMLElement {
  const row = rows[n];
  if (!row) throw new Error(`No row ${n}`);
  return row;
}

describe('InterventionQueue', () => {
  beforeEach(() => {
    fetchInterventions.mockReset();
    assignDeal.mockReset();
  });

  it('shows skeletons while the queue loads', () => {
    fetchInterventions.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<InterventionQueue />);
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('sums up the deals, the gross at risk and who has customers waiting', async () => {
    fetchInterventions.mockResolvedValue(queue);
    renderWithProviders(<InterventionQueue />);

    const summary = await screen.findByRole('region', {name: 'Summary'});
    expect(within(summary).getByText('4')).toBeInTheDocument();
    expect(within(summary).getByText('$13,500')).toBeInTheDocument();
    expect(
      within(summary).getByRole('link', {name: "Open Jordan Reyes's list"}),
    ).toHaveAttribute('href', '/today?user_id=u2');
    expect(within(summary).getByText('2 waiting')).toBeInTheDocument();
  });

  it('words each reason and offers "Assign to me" except on my own deals', async () => {
    fetchInterventions.mockResolvedValue(queue);
    renderWithProviders(<InterventionQueue />);

    const table = await screen.findByRole('table');
    const rows = within(table).getAllByRole('article');
    expect(rows).toHaveLength(4);
    expect(
      within(rowAt(rows, 0)).getByText('Customer waiting 1 hour for an answer'),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 0)).getByText('Assigned to Sam Patel'),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 0)).getByText('$4,500 at risk'),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 1)).getByText(
        'Lead waiting 3 hours with nobody assigned',
      ),
    ).toBeInTheDocument();
    expect(within(rowAt(rows, 1)).getByText('Unassigned')).toBeInTheDocument();
    expect(
      within(rowAt(rows, 2)).getByText('No-show 1 day ago, nobody followed up'),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 2)).queryByRole('button', {name: 'Assign to me'}),
    ).not.toBeInTheDocument();
    expect(
      within(rowAt(rows, 3)).getByText(
        'No activity for 7 days in engaged, more than twice the usual',
      ),
    ).toBeInTheDocument();
  });

  it('assigns the deal to me and refetches the queue', async () => {
    fetchInterventions.mockResolvedValue(queue);
    assignDeal.mockResolvedValue({
      id: 'd2',
      customer: {id: 'c2', name: 'Marcus Lee'},
    });
    renderWithProviders(<InterventionQueue />);

    const table = await screen.findByRole('table');
    const row = rowAt(within(table).getAllByRole('article'), 1);
    await userEvent.click(
      within(row).getByRole('button', {name: 'Assign to me'}),
    );

    expect(assignDeal).toHaveBeenCalledWith('d2', {user_id: 'm1'});
    await waitFor(() => expect(fetchInterventions).toHaveBeenCalledTimes(2));
  });

  it('says so when no deal needs a manager', async () => {
    fetchInterventions.mockResolvedValue({
      items: [],
      summary: {count: 0, at_risk_cents: 0, reps_with_unanswered: []},
    });
    renderWithProviders(<InterventionQueue />);

    expect(
      await screen.findByText('No deal needs you right now.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText('Every customer has an answer.'),
    ).toBeInTheDocument();
  });

  it('shows an error with a way to try again', async () => {
    fetchInterventions.mockRejectedValue(new Error('boom'));
    renderWithProviders(<InterventionQueue />);

    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeInTheDocument();
  });
});
