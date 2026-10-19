import {useQuery, useQueryClient} from '@tanstack/react-query';
import {Check, X} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {attachVehicle, type Deal} from '@/entities/deal';
import {listVehicles, vehiclesKeys} from '@/entities/vehicle';
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
import {Skeleton} from '@/shared/ui/skeleton';
import {dealChanged} from '../lib/cache';
import {errorText} from '../lib/errors';

interface Props {
  deal: Deal;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

interface Picked {
  id: string;
  title: string;
  stock: string;
}

/** The vehicle of interest: one from inventory, a description of what they want, or both. */
export function AttachVehicleDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        {/* The form is mounted on every open, so it starts from the record as it is now. */}
        <AttachVehicleDialogBody {...props} />
      </DialogContent>
    </Dialog>
  );
}

function AttachVehicleDialogBody({deal, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('deals');
  const queryClient = useQueryClient();
  const [term, setTerm] = useState('');
  const [picked, setPicked] = useState<Picked | null>(
    deal.vehicle
      ? {
          id: deal.vehicle.id,
          title: deal.vehicle.title,
          stock: deal.vehicle.stock_number,
        }
      : null,
  );
  const [text, setText] = useState(deal.vehicle_text ?? '');
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const query = {q: term.trim(), per_page: 6};
  const vehicles = useQuery({
    queryKey: vehiclesKeys.list(query),
    queryFn: () => listVehicles(query),
    enabled: open && term.trim().length > 0,
  });

  async function save(event: React.FormEvent) {
    event.preventDefault();
    setError(null);
    setSaving(true);
    try {
      const updated = await attachVehicle(deal.id, {
        vehicle_id: picked?.id ?? null,
        vehicle_text: text.trim() === '' ? null : text.trim(),
      });
      dealChanged(queryClient, updated);
      toast.success(t('vehicle_dialog.saved'));
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('vehicle_dialog.title')}</DialogTitle>
        <DialogDescription>{t('vehicle_dialog.description')}</DialogDescription>
      </DialogHeader>
      <form onSubmit={(e) => void save(e)} className="space-y-4" noValidate>
        {picked && (
          <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/50 px-3 py-2 text-sm">
            <span>
              {t('vehicle_dialog.chosen', {
                title: picked.title,
                stock: picked.stock,
              })}
            </span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              aria-label={t('vehicle_dialog.unpick')}
              onClick={() => setPicked(null)}
            >
              <X aria-hidden />
            </Button>
          </div>
        )}
        <div className="space-y-1.5">
          <Label htmlFor="vehicle_search">{t('vehicle_dialog.search')}</Label>
          <Input
            id="vehicle_search"
            type="search"
            value={term}
            placeholder={t('vehicle_dialog.search_placeholder')}
            onChange={(e) => setTerm(e.target.value)}
          />
        </div>
        {term.trim() !== '' &&
          (vehicles.isPending ? (
            <div
              className="space-y-2"
              aria-busy="true"
              aria-label={t('vehicle_dialog.searching')}
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
                    className="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted"
                    aria-label={t('vehicle_dialog.pick', {
                      title: v.title,
                      stock: v.stock_number,
                    })}
                    aria-pressed={picked?.id === v.id}
                    onClick={() =>
                      setPicked({
                        id: v.id,
                        title: v.title,
                        stock: v.stock_number,
                      })
                    }
                  >
                    <span>
                      <span className="font-medium">{v.title}</span>{' '}
                      <span className="font-mono text-muted-foreground">
                        {v.stock_number}
                      </span>
                    </span>
                    <span className="flex items-center gap-2 text-muted-foreground">
                      <span className="tabular">
                        {v.price_cents == null
                          ? t('common:vehicle.call_for_price')
                          : formatCents(v.price_cents)}
                      </span>
                      {picked?.id === v.id && (
                        <Check aria-hidden className="size-4 text-primary" />
                      )}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">
              {t('vehicle_dialog.no_results')}
            </p>
          ))}
        <div className="space-y-1.5">
          <Label htmlFor="vehicle_text">{t('vehicle_dialog.text')}</Label>
          <Input
            id="vehicle_text"
            value={text}
            maxLength={255}
            placeholder={t('vehicle_dialog.text_placeholder')}
            onChange={(e) => setText(e.target.value)}
          />
        </div>
        {error && (
          <p role="alert" className="text-sm text-danger">
            {error}
          </p>
        )}
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            {t('common:action.cancel')}
          </Button>
          <Button type="submit" disabled={saving}>
            {t('common:action.save')}
          </Button>
        </DialogFooter>
      </form>
    </>
  );
}
