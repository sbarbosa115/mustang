import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {describe, expect, it, vi} from 'vitest';
import type {Vehicle} from '@/entities/vehicle';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {VehicleForm} from './VehicleForm';

const vehicle: Vehicle = {
  id: 'v1',
  stock_number: 'MM-1001',
  year: 2024,
  make: 'Ford',
  model: 'F-150',
  title: '2024 Ford F-150',
  condition: 'used',
  status: 'available',
  created_at: '2026-10-01T00:00:00+00:00',
  price_cents: 4199900,
  mileage: 18250,
  trim: 'XLT',
  vin: null,
  body: null,
  drivetrain: null,
  fuel: null,
  exterior_color: null,
  interior_color: null,
  photo_url: null,
  description: null,
};

async function fillRequired(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText('Stock number'), 'T-100');
  await user.type(screen.getByLabelText('Year'), '2025');
  await user.type(screen.getByLabelText('Make'), 'Toyota');
  await user.type(screen.getByLabelText('Model'), 'Tacoma');
}

describe('VehicleForm', () => {
  it('disables Save until the required fields are valid', async () => {
    const user = userEvent.setup();
    renderWithProviders(<VehicleForm onSubmit={vi.fn()} />);
    const save = screen.getByRole('button', {name: 'Save'});

    expect(save).toBeDisabled();
    await fillRequired(user);
    await waitFor(() => expect(save).toBeEnabled());
  });

  it('sends the price in cents and a blank mileage as null', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    renderWithProviders(<VehicleForm onSubmit={onSubmit} />);
    await fillRequired(user);
    await user.type(screen.getByLabelText('Price ($)'), '24999.50');
    await user.click(screen.getByRole('button', {name: 'Save'}));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit.mock.calls[0]?.[0]).toMatchObject({
      stock_number: 'T-100',
      year: 2025,
      make: 'Toyota',
      model: 'Tacoma',
      condition: 'used',
      price_cents: 2499950,
      mileage: null,
    });
  });

  it('refuses a year outside 1950-2100 and a price that is not money', async () => {
    const user = userEvent.setup();
    renderWithProviders(<VehicleForm onSubmit={vi.fn()} />);
    await fillRequired(user);
    await user.clear(screen.getByLabelText('Year'));
    await user.type(screen.getByLabelText('Year'), '1800');
    await user.type(screen.getByLabelText('Price ($)'), 'cheap');

    expect(
      await screen.findByText('Enter a four-digit year from 1950 to 2100.'),
    ).toBeInTheDocument();
    expect(
      await screen.findByText(/Enter an amount in dollars/),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Save'})).toBeDisabled();
  });

  it('shows a taken stock number next to the stock number field', async () => {
    const error = new ApiError(409, 'stock_number_taken', 'taken');
    renderWithProviders(<VehicleForm onSubmit={vi.fn()} error={error} />);

    expect(
      await screen.findByText('This stock number is already in the inventory.'),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('Stock number')).toHaveAttribute(
      'aria-invalid',
      'true',
    );
  });

  it('opens an existing vehicle with its price in dollars and a fixed stock number', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    renderWithProviders(<VehicleForm vehicle={vehicle} onSubmit={onSubmit} />);

    expect(screen.getByLabelText('Stock number')).toHaveValue('MM-1001');
    expect(screen.getByLabelText('Stock number')).toHaveAttribute('readonly');
    expect(screen.getByLabelText('Price ($)')).toHaveValue('41999');
    await user.click(screen.getByRole('button', {name: 'Save'}));

    await waitFor(() => expect(onSubmit).toHaveBeenCalled());
    expect(onSubmit.mock.calls[0]?.[0]).toMatchObject({
      stock_number: 'MM-1001',
      price_cents: 4199900,
      mileage: 18250,
    });
  });
});
