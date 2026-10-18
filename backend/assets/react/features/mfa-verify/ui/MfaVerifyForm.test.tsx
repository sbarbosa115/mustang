import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {MfaVerifyForm} from './MfaVerifyForm';

const verifyMfa = vi.hoisted(() => vi.fn());
vi.mock('../api', () => ({verifyMfa}));

/** The API refusing, as the entity function does: an ApiError carrying the error code. */
function refuseWith(error: ApiError) {
  verifyMfa.mockImplementation(async () => {
    throw error;
  });
}

const STATUS = {enrolled: true, verified: true, recovery_codes_remaining: 8};

describe('MfaVerifyForm', () => {
  beforeEach(() => {
    verifyMfa.mockReset();
  });

  it('sends the six digits from the app and reports success', async () => {
    verifyMfa.mockResolvedValue(STATUS);
    const onVerified = vi.fn();
    renderWithProviders(<MfaVerifyForm onVerified={onVerified} />);

    const verify = screen.getByRole('button', {name: 'Verify'});
    expect(verify).toBeDisabled();
    await userEvent.type(
      screen.getByLabelText('Code from your authenticator app'),
      '123 456',
    );
    await userEvent.click(verify);

    await waitFor(() => expect(onVerified).toHaveBeenCalledWith(STATUS));
    expect(verifyMfa).toHaveBeenCalledWith({code: '123456'});
  });

  it('says so when the code is wrong, and keeps the user on the step', async () => {
    refuseWith(
      new ApiError(422, 'mfa_invalid_code', 'That code is not right.'),
    );
    const onVerified = vi.fn();
    renderWithProviders(<MfaVerifyForm onVerified={onVerified} />);

    await userEvent.type(
      screen.getByLabelText('Code from your authenticator app'),
      '000000',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Verify'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'That code is not right. Check the app and try again.',
    );
    expect(onVerified).not.toHaveBeenCalled();
  });

  it('asks to wait for the next code when one is used twice', async () => {
    refuseWith(new ApiError(422, 'mfa_code_reused', ''));
    renderWithProviders(<MfaVerifyForm onVerified={vi.fn()} />);

    await userEvent.type(
      screen.getByLabelText('Code from your authenticator app'),
      '123456',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Verify'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'That code was already used. Wait for the next one in the app.',
    );
  });

  it('tells the user to wait after too many wrong codes', async () => {
    refuseWith(new ApiError(429, 'mfa_too_many_attempts', ''));
    renderWithProviders(<MfaVerifyForm onVerified={vi.fn()} />);

    await userEvent.type(
      screen.getByLabelText('Code from your authenticator app'),
      '123456',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Verify'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Too many wrong codes. Wait a few minutes and try again.',
    );
  });

  it('accepts a recovery code instead of the app', async () => {
    verifyMfa.mockResolvedValue({...STATUS, recovery_codes_remaining: 7});
    const onVerified = vi.fn();
    renderWithProviders(<MfaVerifyForm onVerified={onVerified} />);

    await userEvent.click(
      screen.getByRole('button', {name: 'Use a recovery code instead'}),
    );
    expect(
      screen.queryByLabelText('Code from your authenticator app'),
    ).not.toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Recovery code'), 'abcde-fghjk');
    await userEvent.click(screen.getByRole('button', {name: 'Verify'}));

    await waitFor(() => expect(onVerified).toHaveBeenCalled());
    expect(verifyMfa).toHaveBeenCalledWith({recovery_code: 'abcde-fghjk'});
  });
});
