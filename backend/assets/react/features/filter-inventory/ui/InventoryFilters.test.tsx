import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {describe, expect, it, vi} from 'vitest';
import type {VehicleFacets} from '@/entities/vehicle';
import {renderWithProviders} from '@/shared/test/render';
import {EMPTY_FILTERS, type InventoryFilters as Values} from '../model/filters';
import {InventoryFilters} from './InventoryFilters';

const facets: VehicleFacets = {
  makes: [
    {value: 'Ford', count: 12},
    {value: 'Toyota', count: 8},
  ],
  models: [],
  bodies: [],
  drivetrains: [],
  fuels: [],
  colors: [],
  year_min: 2016,
  year_max: 2026,
  price_min_cents: null,
  price_max_cents: null,
};

function Harness({
  initial = EMPTY_FILTERS,
  onChange = vi.fn(),
}: {
  initial?: Values;
  onChange?: (next: Values) => void;
}) {
  const [filters, setFilters] = useState(initial);
  return (
    <InventoryFilters
      filters={filters}
      facets={facets}
      onChange={(next) => {
        setFilters(next);
        onChange(next);
      }}
      onClear={() => setFilters(EMPTY_FILTERS)}
    />
  );
}

describe('InventoryFilters', () => {
  it('searches once typing pauses, not on every key', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderWithProviders(<Harness onChange={onChange} />);

    await user.type(screen.getByLabelText('Search inventory'), 'f-150');

    expect(onChange).not.toHaveBeenCalled();
    await waitFor(() => expect(onChange).toHaveBeenCalledTimes(1), {
      timeout: 1500,
    });
    expect(onChange.mock.calls[0]?.[0]).toMatchObject({q: 'f-150'});
  });

  it('shows how many filters narrow the list and keeps the panel closed by default', () => {
    renderWithProviders(
      <Harness initial={{...EMPTY_FILTERS, make: 'Ford', condition: 'new'}} />,
    );

    const toggle = screen.getByRole('button', {name: /Filters/});
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByLabelText('2 filters applied')).toHaveTextContent('2');
    expect(screen.queryByLabelText('Make')).not.toBeInTheDocument();
  });

  it('offers the facet values with their counts', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness />);

    await user.click(screen.getByRole('button', {name: /Filters/}));
    await user.click(screen.getByRole('combobox', {name: 'Make'}));

    expect(
      await screen.findByRole('option', {name: 'Ford (12)'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('option', {name: 'Toyota (8)'}),
    ).toBeInTheDocument();
  });

  it('applies a price range on Enter and clears everything with Clear filters', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    renderWithProviders(<Harness onChange={onChange} />);

    await user.click(screen.getByRole('button', {name: /Filters/}));
    await user.type(screen.getByLabelText('Price to ($)'), '40000{Enter}');
    expect(onChange).toHaveBeenLastCalledWith(
      expect.objectContaining({priceMax: '40000'}),
    );
    expect(screen.getByLabelText('1 filter applied')).toBeInTheDocument();

    await user.click(screen.getByRole('button', {name: 'Clear filters'}));
    expect(screen.getByLabelText('Price to ($)')).toHaveValue('');
    expect(screen.queryByLabelText('1 filter applied')).not.toBeInTheDocument();
  });
});
