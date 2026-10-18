import {screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {PeriodPicker} from './PeriodPicker';

const onChange = vi.fn();
const value = {from: '2026-09-19', to: '2026-10-18'};

describe('PeriodPicker', () => {
  beforeEach(() => {
    onChange.mockReset();
  });

  it('marks the preset that matches and picks another', async () => {
    renderWithProviders(
      <PeriodPicker value={value} today="2026-10-18" onChange={onChange} />,
    );
    expect(screen.getByRole('button', {name: 'Last 30 days'})).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    await userEvent.click(screen.getByRole('button', {name: 'Last 7 days'}));
    expect(onChange).toHaveBeenCalledWith({
      from: '2026-10-12',
      to: '2026-10-18',
    });
  });

  it('sends a custom range once it is valid, and says when it is not', async () => {
    renderWithProviders(
      <PeriodPicker value={value} today="2026-10-18" onChange={onChange} />,
    );
    const from = screen.getByLabelText('From');
    await userEvent.clear(from);
    await userEvent.type(from, '2026-10-01');
    expect(onChange).toHaveBeenLastCalledWith({
      from: '2026-10-01',
      to: '2026-10-18',
    });

    const to = screen.getByLabelText('To');
    onChange.mockReset();
    await userEvent.clear(to);
    await userEvent.type(to, '2026-09-01');
    expect(onChange).not.toHaveBeenCalled();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Pick a start that is not after the end.',
    );
  });
});
