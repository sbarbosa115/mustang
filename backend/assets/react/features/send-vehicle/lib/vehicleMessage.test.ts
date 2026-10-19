import {describe, expect, it} from 'vitest';
import {createI18n} from '@/shared/i18n';
import {vehicleMessage} from './vehicleMessage';

const i18n = createI18n({strict: true});
const t = i18n.getFixedT('en', 'inbox');

const vehicle = {
  id: 'v1',
  stock_number: 'P1234',
  year: 2022,
  make: 'Honda',
  model: 'Civic',
  title: '2022 Honda Civic',
  condition: 'used',
  status: 'available',
  created_at: '2026-10-01T00:00:00+00:00',
  trim: 'EX',
  mileage: 18250,
  price_cents: 2399900,
} as const;

describe('vehicleMessage', () => {
  it('writes the title, trim, stock number, mileage and price, one per line', () => {
    expect(vehicleMessage(t, vehicle)).toEqual({
      body: '2022 Honda Civic\nTrim: EX\nStock #P1234\nMileage: 18,250 mi\nPrice: $23,999',
      subject: 'The 2022 Honda Civic you asked about',
    });
  });

  it('leaves out what is unknown and says call for price', () => {
    expect(
      vehicleMessage(t, {
        ...vehicle,
        trim: null,
        mileage: null,
        price_cents: null,
      }).body,
    ).toBe('2022 Honda Civic\nStock #P1234\nPrice: call for price');
  });
});
