import {screen} from '@testing-library/react';
import {describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {SettingsImportPage} from './SettingsImportPage';

vi.mock('@/features/upload-import', () => ({UploadImportForm: () => null}));
vi.mock('@/widgets/import-status', () => ({ImportStatus: () => null}));
vi.mock('@/widgets/settings-nav', () => ({
  SettingsTabs: () => <nav aria-label="Settings" />,
}));

describe('SettingsImportPage', () => {
  it('titles the section with a real heading under the page title', () => {
    renderWithProviders(<SettingsImportPage />, {route: '/settings/import'});

    expect(
      screen.getByRole('heading', {
        level: 2,
        name: 'Import customers and leads',
      }),
      'a screen reader lists headings; a styled div is not one',
    ).toBeVisible();
  });
});
