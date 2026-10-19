import {Car} from 'lucide-react';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {formatCents, formatMileage} from '../../lib/money';
import {Badge} from '../badge';
import {Card, CardContent} from '../card';

interface Props {
  title: string;
  trim?: string | null;
  stockNumber: string;
  mileage?: number | null;
  priceCents?: number | null;
  photoUrl?: string | null;
  condition: 'new' | 'used' | 'cpo';
  status: 'available' | 'hold' | 'sold' | 'in_transit';
  action?: ReactNode;
}

/** Photo, year make model, trim, stock number, mileage and price (FR-21). */
export function VehicleCard({
  title,
  trim,
  stockNumber,
  mileage,
  priceCents,
  photoUrl,
  condition,
  status,
  action,
}: Props) {
  const {t} = useTranslation('common');
  return (
    <Card className="gap-0 overflow-hidden py-0">
      <div className="flex aspect-[16/10] items-center justify-center bg-muted">
        {photoUrl ? (
          <img
            src={photoUrl}
            alt={title}
            loading="lazy"
            className="size-full object-cover"
          />
        ) : (
          <Car aria-hidden className="size-10 text-muted-foreground" />
        )}
      </div>
      <CardContent className="space-y-1 p-4">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <Badge variant="secondary">
            {t(`vehicle.condition.${condition}`)}
          </Badge>
          {status !== 'available' && (
            <Badge variant="outline">{t(`vehicle.status.${status}`)}</Badge>
          )}
          <span className="font-mono">{stockNumber}</span>
        </div>
        <h3 className="leading-6 font-semibold">{title}</h3>
        {trim && <p className="text-sm text-muted-foreground">{trim}</p>}
        <div className="tabular flex items-baseline justify-between pt-1">
          <span className="text-sm text-muted-foreground">
            {formatMileage(mileage)}
          </span>
          <span className="font-semibold">
            {priceCents != null
              ? formatCents(priceCents)
              : t('vehicle.call_for_price')}
          </span>
        </div>
        {action && <div className="pt-2">{action}</div>}
      </CardContent>
    </Card>
  );
}
