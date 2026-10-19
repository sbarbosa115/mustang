import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Deal} from '@/entities/deal';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {StageDialog} from './StageDialog';

const changeStage = vi.hoisted(() => vi.fn());
vi.mock('@/entities/deal', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/deal')>()),
  changeStage,
}));

const deal: Deal = {
  id: 'd1',
  customer: {id: 'c1', name: 'Diego Clark'},
  stage: 'engaged',
  stage_entered_at: '2026-10-10T12:00:00+00:00',
  days_in_stage: 3,
  created_at: '2026-10-01T12:00:00+00:00',
  assignee: null,
  lead_id: null,
  vehicle: null,
  vehicle_text: null,
  trade_in: null,
  expected_gross_cents: null,
  lost_reason: null,
  engaged_at: '2026-10-10T12:00:00+00:00',
  appointment_at: null,
  visited_at: null,
  proposal_at: null,
  sold_at: null,
  delivered_at: null,
  lost_at: null,
  last_activity_at: null,
};

function renderDialog(current: Deal = deal) {
  const onOpenChange = vi.fn();
  renderWithProviders(
    <StageDialog deal={current} open onOpenChange={onOpenChange} />,
  );
  return {onOpenChange};
}

describe('StageDialog', () => {
  beforeEach(() => {
    // A block, not an expression: Vitest runs a function returned by beforeEach as the teardown.
    changeStage.mockReset();
  });

  it('offers every other stage, in sales order, each by its name', () => {
    renderDialog();

    expect(
      screen.getByRole('heading', {name: 'Move the deal for Diego Clark'}),
    ).toBeInTheDocument();
    expect(
      screen.getAllByRole('radio').map((r) => r.getAttribute('value')),
    ).toEqual([
      'new',
      'appointment',
      'visit',
      'proposal',
      'sold',
      'delivered',
      'lost',
    ]);
    expect(screen.getByRole('radio', {name: 'New'})).toBeInTheDocument();
    expect(screen.queryByRole('radio', {name: 'Engaged'})).toBeNull();
  });

  it('moves the deal and closes', async () => {
    const user = userEvent.setup();
    changeStage.mockResolvedValue({...deal, stage: 'proposal'});
    const {onOpenChange} = renderDialog();

    await user.click(screen.getByRole('radio', {name: 'Proposal'}));
    await user.click(screen.getByRole('button', {name: 'Move'}));

    await waitFor(() =>
      expect(changeStage).toHaveBeenCalledWith('d1', {
        stage: 'proposal',
        lost_reason: null,
      }),
    );
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
  });

  it('asks why when the deal is lost, and does not send without a reason', async () => {
    const user = userEvent.setup();
    changeStage.mockResolvedValue({...deal, stage: 'lost'});
    renderDialog();

    expect(screen.queryByLabelText('Why was it lost?')).toBeNull();
    await user.click(screen.getByRole('radio', {name: 'Lost'}));
    await user.click(screen.getByRole('button', {name: 'Move'}));

    expect(
      await screen.findByText('Say why the deal was lost.'),
    ).toBeInTheDocument();
    expect(changeStage).not.toHaveBeenCalled();

    await user.type(screen.getByLabelText('Why was it lost?'), 'Price');
    await user.click(screen.getByRole('button', {name: 'Move'}));
    await waitFor(() =>
      expect(changeStage).toHaveBeenCalledWith('d1', {
        stage: 'lost',
        lost_reason: 'Price',
      }),
    );
  });

  it('reopens a lost deal to engaged only', async () => {
    const user = userEvent.setup();
    changeStage.mockResolvedValue({...deal});
    renderDialog({...deal, stage: 'lost', lost_reason: 'Price'});

    expect(
      screen.getByRole('heading', {name: 'Reopen the deal for Diego Clark'}),
    ).toBeInTheDocument();
    expect(screen.queryAllByRole('radio')).toHaveLength(0);
    await user.click(screen.getByRole('button', {name: 'Reopen to engaged'}));

    await waitFor(() =>
      expect(changeStage).toHaveBeenCalledWith('d1', {
        stage: 'engaged',
        lost_reason: null,
      }),
    );
  });

  it('shows a refusal by its code and stays open', async () => {
    const user = userEvent.setup();
    changeStage.mockImplementation(() =>
      Promise.reject(
        new ApiError(409, 'open_deal_exists', 'The customer already has one.'),
      ),
    );
    const {onOpenChange} = renderDialog({
      ...deal,
      stage: 'lost',
      lost_reason: 'Price',
    });

    await user.click(screen.getByRole('button', {name: 'Reopen to engaged'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'This customer already has an open deal.',
    );
    expect(onOpenChange).not.toHaveBeenCalledWith(false);
  });
});
