import {screen} from '@testing-library/react';
import {describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {SettingsRoutingPage} from './SettingsRoutingPage';

vi.mock('@/entities/lead', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/lead')>()),
  fetchRouting: vi.fn(() => new Promise(() => {})),
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  listUsers: vi.fn(() => new Promise(() => {})),
}));
vi.mock('@/widgets/settings-nav', () => ({
  SettingsTabs: () => <nav aria-label="Settings" />,
}));

describe('SettingsRoutingPage', () => {
  it('titles the section with a real heading under the page title', () => {
    renderWithProviders(<SettingsRoutingPage />, {route: '/settings/routing'});

    expect(
      screen.getByRole('heading', {level: 2, name: 'Lead routing'}),
      'a screen reader lists headings; a styled div is not one',
    ).toBeVisible();
  });
});
