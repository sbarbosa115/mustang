import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {describe, expect, it, vi} from 'vitest';
import {ThemeProvider} from '@/shared/lib/theme';
import {renderWithProviders} from '@/shared/test/render';
import {ThemeSwitch} from './ThemeSwitch';

const updatePreferences = vi.hoisted(() => vi.fn());
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  updatePreferences,
}));

describe('ThemeSwitch', () => {
  it('applies dark at once and saves the choice for every device', async () => {
    updatePreferences.mockResolvedValue({user: {theme: 'dark'}});
    renderWithProviders(
      <ThemeProvider>
        <ThemeSwitch />
      </ThemeProvider>,
    );

    await userEvent.click(screen.getByText('Dark'));

    expect(document.documentElement).toHaveClass('dark');
    await waitFor(() =>
      expect(updatePreferences).toHaveBeenCalledWith({theme: 'dark'}),
    );
  });

  it('goes back to light', async () => {
    updatePreferences.mockResolvedValue({user: {theme: 'light'}});
    renderWithProviders(
      <ThemeProvider>
        <ThemeSwitch />
      </ThemeProvider>,
    );
    await userEvent.click(screen.getByText('Light'));
    expect(document.documentElement).not.toHaveClass('dark');
  });
});
