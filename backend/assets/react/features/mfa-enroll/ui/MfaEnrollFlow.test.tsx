import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {MfaEnrollFlow} from './MfaEnrollFlow';

const api = vi.hoisted(() => ({enrollMfa: vi.fn(), confirmMfa: vi.fn()}));
vi.mock('../api', () => api);

const CODES = [
  'AAAAA-BBBBB',
  'CCCCC-DDDDD',
  'EEEEE-FFFFF',
  'GGGGG-HHHHH',
  'JJJJJ-KKKKK',
  'MMMMM-NNNNN',
  'PPPPP-QQQQQ',
  'RRRRR-SSSSS',
];

describe('MfaEnrollFlow', () => {
  beforeEach(() => {
    api.enrollMfa.mockReset();
    api.confirmMfa.mockReset();
    api.enrollMfa.mockResolvedValue({
      secret: 'JBSWY3DPEHPK3PXPJBSWY3DP',
      otpauth_url: 'otpauth://totp/Mustang:sam?secret=JBSWY3DPEHPK3PXPJBSWY3DP',
      qr_svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    });
  });

  it('shows the QR code and the key to type, then the recovery codes once the code is right', async () => {
    api.confirmMfa.mockResolvedValue(CODES);
    const onDone = vi.fn();
    renderWithProviders(<MfaEnrollFlow onDone={onDone} />);

    expect(
      await screen.findByRole('img', {
        name: 'QR code to add Mustang to your authenticator app',
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByText('JBSW Y3DP EHPK 3PXP JBSW Y3DP'),
    ).toBeInTheDocument();
    expect(api.enrollMfa).toHaveBeenCalledTimes(1);

    await userEvent.type(
      screen.getByLabelText('Code from your authenticator app'),
      '123456',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Turn on'}));

    const list = await screen.findByRole('list', {name: 'Recovery codes'});
    expect(within(list).getAllByRole('listitem')).toHaveLength(8);
    expect(api.confirmMfa).toHaveBeenCalledWith('123456');
    expect(onDone).not.toHaveBeenCalled();

    await userEvent.click(
      screen.getByRole('button', {name: 'I saved my codes'}),
    );
    expect(onDone).toHaveBeenCalled();
  });

  it('keeps the user on the step when the code is wrong', async () => {
    api.confirmMfa.mockImplementation(async () => {
      throw new ApiError(422, 'mfa_invalid_code', '');
    });
    renderWithProviders(<MfaEnrollFlow onDone={vi.fn()} />);

    await userEvent.type(
      await screen.findByLabelText('Code from your authenticator app'),
      '000000',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Turn on'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'That code is not right. Check the app and try again.',
    );
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
  });

  it('explains when enrollment cannot start', async () => {
    api.enrollMfa.mockReset();
    api.enrollMfa.mockImplementation(async () => {
      throw new ApiError(409, 'mfa_already_enrolled', '');
    });
    renderWithProviders(<MfaEnrollFlow onDone={vi.fn()} />);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Two-step sign-in is already set up for this account. Ask an admin to reset it.',
    );
  });

  it('waits for the key before asking for a code', async () => {
    api.enrollMfa.mockReset();
    api.enrollMfa.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<MfaEnrollFlow onDone={vi.fn()} />);

    expect(screen.getByLabelText('Preparing your key')).toHaveAttribute(
      'aria-busy',
      'true',
    );
    await waitFor(() =>
      expect(
        screen.queryByRole('button', {name: 'Turn on'}),
      ).not.toBeInTheDocument(),
    );
  });
});
