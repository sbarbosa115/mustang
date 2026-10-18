import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Deal} from '@/entities/deal';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {DealBoard} from './DealBoard';

const listDeals = vi.hoisted(() => vi.fn());
vi.mock('@/entities/deal', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/deal')>()),
  listDeals,
}));

function deal(overrides: Partial<Deal>): Deal {
  return {
    id: 'd1',
    customer: {id: 'c1', name: 'Diego Clark'},
    stage: 'new',
    stage_entered_at: '2026-10-10T12:00:00+00:00',
    days_in_stage: 0,
    created_at: '2026-10-10T12:00:00+00:00',
    assignee: null,
    lead_id: null,
    vehicle: null,
    vehicle_text: null,
    trade_in: null,
    expected_gross_cents: null,
    lost_reason: null,
    engaged_at: null,
    appointment_at: null,
    visited_at: null,
    proposal_at: null,
    sold_at: null,
    delivered_at: null,
    lost_at: null,
    last_activity_at: null,
    ...overrides,
  };
}

const page = (items: Deal[], total = items.length) => ({
  items,
  total,
  page: 1,
  per_page: 100,
});

describe('DealBoard', () => {
  beforeEach(() => {
    listDeals.mockReset();
  });

  it('puts each open deal in its stage column with the customer, vehicle, days in stage and rep', async () => {
    listDeals.mockResolvedValue(
      page([
        deal({
          id: 'd1',
          stage: 'proposal',
          days_in_stage: 3,
          vehicle: {
            id: 'v1',
            title: '2026 Ford F-150',
            stock_number: 'MM-1001',
          },
          assignee: {id: 'u1', name: 'Sam Rivera'},
        }),
        deal({
          id: 'd2',
          customer: {id: 'c2', name: 'Ana White'},
          stage: 'new',
          days_in_stage: 1,
          vehicle_text: 'Red convertible',
        }),
      ]),
    );
    renderWithProviders(<DealBoard />, {route: '/deals'});

    const proposal = await screen.findByRole('region', {name: 'Proposal, 1'});
    expect(
      within(proposal).getByRole('link', {
        name: 'Open the deal for Diego Clark',
      }),
    ).toHaveAttribute('href', '/deals/d1');
    expect(within(proposal).getByText('2026 Ford F-150')).toBeInTheDocument();
    expect(within(proposal).getByText('3 days in stage')).toBeInTheDocument();
    expect(within(proposal).getByText('Sam Rivera')).toBeInTheDocument();

    const fresh = screen.getByRole('region', {name: 'New, 1'});
    expect(within(fresh).getByText('Ana White')).toBeInTheDocument();
    expect(within(fresh).getByText('Red convertible')).toBeInTheDocument();
    expect(within(fresh).getByText('1 day in stage')).toBeInTheDocument();
    expect(within(fresh).getByText('Unassigned')).toBeInTheDocument();

    const engaged = screen.getByRole('region', {name: 'Engaged, 0'});
    expect(
      within(engaged).getByText('No deals in this stage'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('region', {name: /^Lost/})).toBeNull();
    expect(listDeals).toHaveBeenCalledWith(
      expect.objectContaining({open: true, per_page: 100}),
    );
  });

  it('shows skeletons while loading', () => {
    listDeals.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<DealBoard />, {route: '/deals'});

    expect(screen.getByLabelText('Loading deals')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('says what a deal is when there are none', async () => {
    listDeals.mockResolvedValue(page([]));
    renderWithProviders(<DealBoard />, {route: '/deals'});

    expect(await screen.findByText('No open deals yet')).toBeInTheDocument();
    expect(
      screen.getByText(/A deal opens when a lead comes in/),
    ).toBeInTheDocument();
  });

  it('offers a way back when the filters match nothing', async () => {
    const user = userEvent.setup();
    listDeals.mockImplementation((query: {q?: string}) =>
      Promise.resolve(page(query.q ? [] : [deal({})])),
    );
    renderWithProviders(<DealBoard />, {route: '/deals?q=zzz'});

    expect(
      await screen.findByText('No deals match these filters.'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Show all'}));

    expect(await screen.findByText('Diego Clark')).toBeInTheDocument();
    expect(screen.getByLabelText('Search by customer or vehicle')).toHaveValue(
      '',
    );
  });

  it('narrows to my deals', async () => {
    const user = userEvent.setup();
    listDeals.mockResolvedValue(page([deal({})]));
    renderWithProviders(<DealBoard userId="u7" />, {route: '/deals'});
    await screen.findByText('Diego Clark');

    await user.click(screen.getByRole('radio', {name: 'My deals'}));

    await waitFor(() =>
      expect(listDeals).toHaveBeenLastCalledWith(
        expect.objectContaining({assigned_to: 'u7'}),
      ),
    );
  });

  it('lists lost deals with their reason', async () => {
    const user = userEvent.setup();
    listDeals.mockImplementation((query: {stage?: string}) =>
      Promise.resolve(
        page(
          query.stage === 'lost'
            ? [deal({stage: 'lost', lost_reason: 'Bought elsewhere'})]
            : [],
        ),
      ),
    );
    renderWithProviders(<DealBoard />, {route: '/deals'});
    await screen.findByText('No open deals yet');

    await user.click(screen.getByRole('radio', {name: 'Lost'}));

    expect(
      await screen.findByText('Lost: Bought elsewhere'),
    ).toBeInTheDocument();
    expect(listDeals).toHaveBeenLastCalledWith(
      expect.objectContaining({stage: 'lost'}),
    );
  });

  it('opens the stage dialog from a card', async () => {
    const user = userEvent.setup();
    listDeals.mockResolvedValue(page([deal({stage: 'engaged'})]));
    renderWithProviders(<DealBoard />, {route: '/deals'});

    await user.click(
      await screen.findByRole('button', {
        name: 'Move the deal for Diego Clark',
      }),
    );

    expect(
      await screen.findByRole('heading', {
        name: 'Move the deal for Diego Clark',
      }),
    ).toBeInTheDocument();
  });

  it('says so when the deals cannot be loaded, with a retry', async () => {
    listDeals.mockRejectedValue(new ApiError(500, 'http_500', 'boom'));
    renderWithProviders(<DealBoard />, {route: '/deals'});

    expect(
      await screen.findByText('The deals could not be loaded.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Try again'})).toBeInTheDocument();
  });
});
