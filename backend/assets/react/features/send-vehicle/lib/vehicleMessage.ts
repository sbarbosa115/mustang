import type {TFunction} from 'i18next';
import type {Vehicle} from '@/entities/vehicle';
import {formatCents, formatMileage} from '@/shared/lib/money';

type VehicleDetails = Pick<
  Vehicle,
  'title' | 'stock_number' | 'trim' | 'mileage' | 'price_cents'
>;

/** A vehicle's details as a message body (and an email subject): no attachments, one fact per line. */
export function vehicleMessage(
  t: TFunction<'inbox'>,
  vehicle: VehicleDetails,
): {body: string; subject: string} {
  const lines = [
    vehicle.title,
    vehicle.trim ? t('vehicle.message.trim', {trim: vehicle.trim}) : null,
    t('vehicle.message.stock', {stock: vehicle.stock_number}),
    vehicle.mileage != null
      ? t('vehicle.message.mileage', {mileage: formatMileage(vehicle.mileage)})
      : null,
    vehicle.price_cents != null
      ? t('vehicle.message.price', {price: formatCents(vehicle.price_cents)})
      : t('vehicle.message.call_for_price'),
  ];
  return {
    body: lines.filter((line): line is string => line !== null).join('\n'),
    subject: t('vehicle.message.subject', {title: vehicle.title}),
  };
}
