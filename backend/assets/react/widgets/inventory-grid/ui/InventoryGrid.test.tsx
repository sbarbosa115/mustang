import {screen} from '@testing-library/react';
import {describe, expect, it, vi} from 'vitest';
import type {Vehicle} from '@/entities/vehicle';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {InventoryGrid} from './InventoryGrid';

const listVehicles = vi.hoisted(() => vi.fn());
const fetchVehicleFacets = vi.hoisted(() => vi.fn());
vi.mock('@/entities/vehicle', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/vehicle')>()),
  listVehicles,
  fetchVehicleFacets,
}));

const ford: Vehicle = {
  id: 'v1',
  stock_number: 'MM-1001',
  year: 2024,
  make: 'Ford',
  model: 'F-150',
  title: '2024 Ford F-150',
  condition: 'used',
  status: 'hold',
  created_at: '2026-10-01T00:00:00+00:00',
  price_cents: null,
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
const emptyFacets = {
  makes: [],
  models: [],
  bodies: [],
  drivetrains: [],
  fuels: [],
  colors: [],
  year_min: null,
  year_max: null,
  price_min_cents: null,
  price_max_cents: null,
};

describe('InventoryGrid', () => {
  it('shows a vehicle with its status in words and "Call for price" when it has no price', async () => {
    fetchVehicleFacets.mockResolvedValue(emptyFacets);
    listVehicles.mockResolvedValue({
      items: [ford],
      total: 1,
      page: 1,
      per_page: 25,
    });
    renderWithProviders(<InventoryGrid />, {route: '/inventory'});

    expect((await screen.findAllByText('MM-1001')).length).toBeGreaterThan(0);
    expect(screen.getAllByText('On hold').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Call for price').length).toBeGreaterThan(0);
    expect(
      screen.getAllByRole('link', {name: /2024 Ford F-150/}).length,
    ).toBeGreaterThan(0);
  });

  it('says so when the dealership has no vehicles', async () => {
    fetchVehicleFacets.mockResolvedValue(emptyFacets);
    listVehicles.mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      per_page: 25,
    });
    renderWithProviders(<InventoryGrid />, {route: '/inventory'});

    expect(await screen.findByText(/No vehicles yet/)).toBeInTheDocument();
  });

  it('offers a way back when filters leave nothing', async () => {
    fetchVehicleFacets.mockResolvedValue(emptyFacets);
    listVehicles.mockResolvedValue({
      items: [],
      total: 0,
      page: 1,
      per_page: 25,
    });
    renderWithProviders(<InventoryGrid />, {route: '/inventory?make=Edsel'});

    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Show all'})).toBeInTheDocument();
  });

  it('shows an error with a retry', async () => {
    fetchVehicleFacets.mockResolvedValue(emptyFacets);
    listVehicles.mockRejectedValue(new ApiError(500, 'http_500', 'boom'));
    renderWithProviders(<InventoryGrid />, {route: '/inventory'});

    expect(
      await screen.findByText('This list could not be loaded.'),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'Try again'})).toBeInTheDocument();
  });
});
