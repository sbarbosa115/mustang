import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {SettingsSuppressionsPage} from './SettingsSuppressionsPage';

const listSuppressions = vi.hoisted(() => vi.fn());
vi.mock('@/entities/consent', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/consent')>()),
  listSuppressions,
}));
vi.mock('@/widgets/settings-nav', () => ({
  SettingsTabs: () => <nav aria-label="Settings" />,
}));

const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});
const row = {
  id: 's1',
  contact_value: '+18135551025',
  channel: 'text',
  reason: 'stop_keyword',
  created_at: '2026-10-12T12:00:00+00:00',
};

describe('SettingsSuppressionsPage', () => {
  beforeEach(() => listSuppressions.mockReset());

  it('lists opted-out contacts with the reason in words', async () => {
    listSuppressions.mockResolvedValue(page([row]));
    renderWithProviders(<SettingsSuppressionsPage />, {
      route: '/settings/suppressions',
    });

    expect(
      (await screen.findAllByText('(813) 555-1025'))[0],
    ).toBeInTheDocument();
    expect(screen.getAllByText(/Replied STOP/)[0]).toBeInTheDocument();
  });

  it('says when nobody opted out', async () => {
    listSuppressions.mockResolvedValue(page([]));
    renderWithProviders(<SettingsSuppressionsPage />, {
      route: '/settings/suppressions',
    });

    expect(await screen.findByText('Nobody has opted out.')).toBeVisible();
  });

  it('searches, and a search that finds nothing offers Show all', async () => {
    const user = userEvent.setup();
    listSuppressions.mockResolvedValue(page([]));
    renderWithProviders(<SettingsSuppressionsPage />, {
      route: '/settings/suppressions?q=zzz',
    });

    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeVisible();
    expect(listSuppressions).toHaveBeenCalledWith(
      expect.objectContaining({q: 'zzz'}),
    );
    listSuppressions.mockResolvedValue(page([row]));
    await user.click(screen.getByRole('button', {name: 'Show all'}));

    await waitFor(() =>
      expect(screen.getByLabelText('Search opt-outs')).toHaveValue(''),
    );
    expect(
      (await screen.findAllByText('(813) 555-1025'))[0],
    ).toBeInTheDocument();
  });
});
