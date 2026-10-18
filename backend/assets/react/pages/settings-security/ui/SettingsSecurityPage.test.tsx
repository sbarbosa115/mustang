import {screen} from '@testing-library/react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {SettingsSecurityPage} from './SettingsSecurityPage';

const fetchMfaStatus = vi.hoisted(() => vi.fn());
vi.mock('../api', () => ({mfaStatusKey: ['mfa', 'status'], fetchMfaStatus}));
vi.mock('@/widgets/settings-nav', () => ({SettingsTabs: () => null}));

describe('SettingsSecurityPage', () => {
  beforeEach(() => {
    fetchMfaStatus.mockReset();
  });

  it('shows two-step sign-in on, with the recovery codes left', async () => {
    fetchMfaStatus.mockResolvedValue({
      enrolled: true,
      verified: true,
      recovery_codes_remaining: 7,
    });
    renderWithProviders(<SettingsSecurityPage />);

    expect(await screen.findByText('On')).toBeInTheDocument();
    expect(screen.getByText('7 recovery codes left')).toBeInTheDocument();
    expect(
      screen.queryByText(/Running low on recovery codes/),
    ).not.toBeInTheDocument();
  });

  it('warns when the recovery codes run low', async () => {
    fetchMfaStatus.mockResolvedValue({
      enrolled: true,
      verified: true,
      recovery_codes_remaining: 1,
    });
    renderWithProviders(<SettingsSecurityPage />);

    expect(await screen.findByText('1 recovery code left')).toBeInTheDocument();
    expect(
      screen.getByText(/Running low on recovery codes/),
    ).toBeInTheDocument();
  });

  it('says when an admin has reset it', async () => {
    fetchMfaStatus.mockResolvedValue({
      enrolled: false,
      verified: true,
      recovery_codes_remaining: 0,
    });
    renderWithProviders(<SettingsSecurityPage />);

    expect(await screen.findByText('Off')).toBeInTheDocument();
    expect(
      screen.getByText(/set it up again the next time you sign in/),
    ).toBeInTheDocument();
  });
});
