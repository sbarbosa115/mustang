import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Schema} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {ResetMfaDialog} from './ResetMfaDialog';

const resetUserMfa = vi.hoisted(() => vi.fn());
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  resetUserMfa,
}));

const user = {id: 'u2', name: 'Sam Patel'} as Schema<'UserOutput'>;

describe('ResetMfaDialog', () => {
  beforeEach(() => {
    resetUserMfa.mockReset();
  });

  it('names the user and resets only after the confirm button', async () => {
    resetUserMfa.mockResolvedValue(undefined);
    const onOpenChange = vi.fn();
    renderWithProviders(
      <ResetMfaDialog user={user} open onOpenChange={onOpenChange} />,
    );
    expect(
      screen.getByText('Reset two-step sign-in for Sam Patel?'),
    ).toBeVisible();
    expect(resetUserMfa).not.toHaveBeenCalled();

    await userEvent.click(
      screen.getByRole('button', {name: 'Reset for Sam Patel'}),
    );

    await waitFor(() => expect(resetUserMfa).toHaveBeenCalledWith('u2'));
    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
  });
});
