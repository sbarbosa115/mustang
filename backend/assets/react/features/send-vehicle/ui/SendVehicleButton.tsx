import {useQuery} from '@tanstack/react-query';
import {Car} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {listVehicles, vehiclesKeys, type Vehicle} from '@/entities/vehicle';
import {formatCents} from '@/shared/lib/money';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {VehicleCard} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';
import {vehicleMessage} from '../lib/vehicleMessage';

interface Props {
  channel: 'text' | 'email';
  disabled?: boolean;
  /** The vehicle's details as a message body and subject; the composer sends them like any message. */
  onAdd: (draft: {body: string; subject: string | null}) => void;
}

/** "Vehicle": search inventory, see the vehicle card and add its details to the message (FR-21). */
export function SendVehicleButton({channel, disabled, onAdd}: Props) {
  const {t} = useTranslation('inbox');
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled}
        onClick={() => setOpen(true)}
      >
        <Car aria-hidden />
        {t('vehicle.open')}
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
          {open && (
            <VehiclePicker
              onAdd={(vehicle) => {
                const draft = vehicleMessage(t, vehicle);
                onAdd({
                  body: draft.body,
                  subject: channel === 'email' ? draft.subject : null,
                });
                setOpen(false);
              }}
              onCancel={() => setOpen(false)}
            />
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}

function VehiclePicker({
  onAdd,
  onCancel,
}: {
  onAdd: (vehicle: Vehicle) => void;
  onCancel: () => void;
}) {
  const {t} = useTranslation('inbox');
  const [term, setTerm] = useState('');
  const [picked, setPicked] = useState<Vehicle | null>(null);
  const query = {q: term.trim(), per_page: 6};
  const vehicles = useQuery({
    queryKey: vehiclesKeys.list(query),
    queryFn: () => listVehicles(query),
    enabled: term.trim().length > 0,
  });

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('vehicle.title')}</DialogTitle>
        <DialogDescription>{t('vehicle.description')}</DialogDescription>
      </DialogHeader>
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="send_vehicle_search">{t('vehicle.search')}</Label>
          <Input
            id="send_vehicle_search"
            type="search"
            value={term}
            placeholder={t('vehicle.search_placeholder')}
            onChange={(event) => setTerm(event.target.value)}
          />
        </div>
        {term.trim() !== '' &&
          (vehicles.isPending ? (
            <div
              className="space-y-2"
              aria-busy="true"
              aria-label={t('vehicle.searching')}
            >
              <Skeleton className="h-11 w-full" />
              <Skeleton className="h-11 w-full" />
            </div>
          ) : vehicles.data && vehicles.data.items.length > 0 ? (
            <ul className="divide-y rounded-md border">
              {vehicles.data.items.map((v) => (
                <li key={v.id}>
                  <button
                    type="button"
                    className="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted aria-pressed:bg-accent"
                    aria-label={t('vehicle.pick', {
                      title: v.title,
                      stock: v.stock_number,
                    })}
                    aria-pressed={picked?.id === v.id}
                    onClick={() => setPicked(v)}
                  >
                    <span>
                      <span className="font-medium">{v.title}</span>{' '}
                      <span className="font-mono text-muted-foreground">
                        {v.stock_number}
                      </span>
                    </span>
                    <span className="tabular text-muted-foreground">
                      {v.price_cents == null
                        ? t('common:vehicle.call_for_price')
                        : formatCents(v.price_cents)}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">
              {t('vehicle.no_results')}
            </p>
          ))}
        {picked && (
          <div className="mx-auto max-w-xs">
            <VehicleCard
              title={picked.title}
              trim={picked.trim}
              stockNumber={picked.stock_number}
              mileage={picked.mileage}
              priceCents={picked.price_cents}
              photoUrl={picked.photo_url}
              condition={picked.condition}
              status={picked.status}
            />
          </div>
        )}
      </div>
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('common:action.cancel')}
        </Button>
        <Button
          type="button"
          disabled={!picked}
          onClick={() => picked && onAdd(picked)}
        >
          {t('vehicle.add')}
        </Button>
      </DialogFooter>
    </>
  );
}
