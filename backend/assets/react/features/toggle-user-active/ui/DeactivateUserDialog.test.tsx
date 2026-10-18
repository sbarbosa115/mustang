import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError, type Schema} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {DeactivateUserDialog} from './DeactivateUserDialog';

const deactivateUser = vi.hoisted(() => vi.fn());
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  deactivateUser,
}));

const user = {id: 'u2', name: 'Sam Patel'} as Schema<'UserOutput'>;

describe('DeactivateUserDialog', () => {
  beforeEach(() => {
    deactivateUser.mockReset();
  });

  it('names the user and deactivates only after the confirm button', async () => {
    deactivateUser.mockResolvedValue({});
    const onOpenChange = vi.fn();
    renderWithProviders(
      <DeactivateUserDialog user={user} open onOpenChange={onOpenChange} />,
    );
    expect(screen.getByText('Deactivate Sam Patel?')).toBeVisible();
    expect(deactivateUser).not.toHaveBeenCalled();

    await userEvent.click(
      screen.getByRole('button', {name: 'Deactivate Sam Patel'}),
    );

    await waitFor(() => expect(deactivateUser).toHaveBeenCalledWith('u2'));
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
  });

  it('shows the refusal when they are the last admin and stays open', async () => {
    deactivateUser.mockImplementation(() =>
      Promise.reject(new ApiError(409, 'last_admin', 'no')),
    );
    const onOpenChange = vi.fn();
    renderWithProviders(
      <DeactivateUserDialog user={user} open onOpenChange={onOpenChange} />,
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Deactivate Sam Patel'}),
    );

    expect(await screen.findByText(/at least one active admin/)).toBeVisible();
    expect(onOpenChange).not.toHaveBeenCalledWith(false);
  });
});
