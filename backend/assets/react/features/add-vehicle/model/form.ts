import {z} from 'zod';
import type {Vehicle, VehicleInput} from '@/entities/vehicle';

export type {Vehicle, VehicleInput};

const text = (max: number) => z.string().trim().max(max, 'too_long');
const whole = z
  .string()
  .trim()
  .refine((v) => v === '' || /^\d{1,9}$/.test(v), 'whole_number');

/** Error messages here are keys under `form.error` in the inventory namespace. */
export const vehicleSchema = z.object({
  stock_number: text(40).min(1, 'required'),
  year: z
    .string()
    .trim()
    .min(1, 'required')
    .refine(
      (v) => /^\d{4}$/.test(v) && Number(v) >= 1950 && Number(v) <= 2100,
      'year_range',
    ),
  make: text(60).min(1, 'required'),
  model: text(60).min(1, 'required'),
  condition: z.enum(['new', 'used', 'cpo'], {message: 'required'}),
  trim: text(80),
  vin: text(17),
  body: text(40),
  drivetrain: text(20),
  fuel: text(20),
  exterior_color: text(40),
  interior_color: text(40),
  mileage: whole,
  price: z
    .string()
    .trim()
    .refine((v) => v === '' || /^\d{1,9}(\.\d{1,2})?$/.test(v), 'price'),
  photo_url: text(500).refine(
    (v) => v === '' || /^https?:\/\/\S+$/i.test(v),
    'photo_url',
  ),
  description: text(5000),
});

/** What the inputs hold: always text, because that is what a person types. */
export type VehicleFormValues = z.infer<typeof vehicleSchema>;

export const EMPTY_VALUES: VehicleFormValues = {
  stock_number: '',
  year: '',
  make: '',
  model: '',
  condition: 'used',
  trim: '',
  vin: '',
  body: '',
  drivetrain: '',
  fuel: '',
  exterior_color: '',
  interior_color: '',
  mileage: '',
  price: '',
  photo_url: '',
  description: '',
};

function dollars(cents: number | null | undefined): string {
  if (cents == null) return '';
  return cents % 100 === 0 ? String(cents / 100) : (cents / 100).toFixed(2);
}

export function valuesFromVehicle(vehicle: Vehicle): VehicleFormValues {
  return {
    stock_number: vehicle.stock_number,
    year: String(vehicle.year),
    make: vehicle.make,
    model: vehicle.model,
    condition: vehicle.condition,
    trim: vehicle.trim ?? '',
    vin: vehicle.vin ?? '',
    body: vehicle.body ?? '',
    drivetrain: vehicle.drivetrain ?? '',
    fuel: vehicle.fuel ?? '',
    exterior_color: vehicle.exterior_color ?? '',
    interior_color: vehicle.interior_color ?? '',
    mileage: vehicle.mileage == null ? '' : String(vehicle.mileage),
    price: dollars(vehicle.price_cents),
    photo_url: vehicle.photo_url ?? '',
    description: vehicle.description ?? '',
  };
}

/**
 * The API body: the price goes from dollars to cents, and a blank number is null (the API leaves it as it is). An empty text is sent as ''
 * (a create ignores it, an edit uses it to clear the field).
 */
export function toVehicleInput(values: VehicleFormValues): VehicleInput {
  const cents =
    values.price === '' ? null : Math.round(Number(values.price) * 100);
  return {
    stock_number: values.stock_number,
    year: Number(values.year),
    make: values.make,
    model: values.model,
    condition: values.condition,
    trim: values.trim,
    vin: values.vin,
    body: values.body,
    drivetrain: values.drivetrain,
    fuel: values.fuel,
    exterior_color: values.exterior_color,
    interior_color: values.interior_color,
    mileage: values.mileage === '' ? null : Number(values.mileage),
    price_cents: cents,
    photo_url: values.photo_url,
    description: values.description,
  };
}
