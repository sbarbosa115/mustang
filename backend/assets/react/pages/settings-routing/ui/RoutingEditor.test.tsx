import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Schema} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {RoutingEditor} from './RoutingEditor';

const saveRouting = vi.hoisted(() => vi.fn());
vi.mock('@/entities/lead', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/lead')>()),
  saveRouting,
}));

const user = (id: string, name: string) =>
  ({id, name, role: 'sales', active: true}) as Schema<'UserOutput'>;
const sales = [
  user('u1', 'Jordan Reyes'),
  user('u2', 'Sam Patel'),
  user('u3', 'Ana New'),
];
const routing = {
  users: [
    {id: 'u2', name: 'Sam Patel'},
    {id: 'u1', name: 'Jordan Reyes'},
  ],
  next: {id: 'u2', name: 'Sam Patel'},
};

describe('RoutingEditor', () => {
  beforeEach(() => {
    saveRouting.mockReset();
  });

  it('lists the rotation in order, then the salespeople left out, and names the next rep', () => {
    renderWithProviders(<RoutingEditor routing={routing} sales={sales} />);
    const rows = within(screen.getByRole('list')).getAllByRole('listitem');
    expect(rows.map((r) => r.textContent)).toEqual([
      '1.Sam Patel',
      '2.Jordan Reyes',
      'Ana NewNot in the rotation',
    ]);
    expect(screen.getByText('Next lead goes to Sam Patel.')).toBeVisible();
    expect(screen.getByRole('button', {name: 'Save order'})).toBeDisabled();
  });

  it('saves a new order with a rep added and moved', async () => {
    saveRouting.mockResolvedValue({
      users: [
        {id: 'u1', name: 'Jordan Reyes'},
        {id: 'u2', name: 'Sam Patel'},
        {id: 'u3', name: 'Ana New'},
      ],
      next: {id: 'u1', name: 'Jordan Reyes'},
    });
    renderWithProviders(<RoutingEditor routing={routing} sales={sales} />);
    await userEvent.click(
      screen.getByRole('checkbox', {name: 'Include Ana New'}),
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Move Jordan Reyes up'}),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Save order'}));

    await waitFor(() =>
      expect(saveRouting).toHaveBeenCalledWith({
        user_ids: ['u1', 'u2', 'u3'],
      }),
    );
  });

  it('can empty the rotation', async () => {
    saveRouting.mockResolvedValue({users: [], next: null});
    renderWithProviders(<RoutingEditor routing={routing} sales={sales} />);
    await userEvent.click(
      screen.getByRole('checkbox', {name: 'Include Sam Patel'}),
    );
    await userEvent.click(
      screen.getByRole('checkbox', {name: 'Include Jordan Reyes'}),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Save order'}));
    await waitFor(() =>
      expect(saveRouting).toHaveBeenCalledWith({user_ids: []}),
    );
  });
});
