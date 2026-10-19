import {zodResolver} from '@hookform/resolvers/zod';
import {useQueryClient} from '@tanstack/react-query';
import {Car, Plus, Trash2} from 'lucide-react';
import {useState} from 'react';
import {useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {z} from 'zod';
import {
  addOwnedVehicle,
  customersKeys,
  removeOwnedVehicle,
  type Customer,
} from '@/entities/customer';
import {ApiError} from '@/shared/api';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import {Button} from '@/shared/ui/button';
import {Card, CardContent, CardHeader, CardTitle} from '@/shared/ui/card';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {errorText} from '../lib/errors';

type Vehicle = Customer['owned_vehicles'][number];

const schema = z
  .object({
    year: z.string().trim(),
    make: z.string().trim().max(60),
    model: z.string().trim().max(60),
    trim: z.string().trim().max(60),
    vin: z.string().trim().max(17),
    purchased_at: z.string().trim(),
  })
  .refine(
    (v) => v.year !== '' || v.make !== '' || v.model !== '' || v.vin !== '',
    {
      path: ['make'],
      message: 'empty',
    },
  )
  .refine(
    (v) =>
      v.year === '' ||
      (/^\d{4}$/.test(v.year) && +v.year >= 1950 && +v.year <= 2100),
    {
      path: ['year'],
      message: 'year',
    },
  );
type Values = z.infer<typeof schema>;

function title(vehicle: Vehicle, fallback: string): string {
  const parts = [
    vehicle.year,
    vehicle.make,
    vehicle.model,
    vehicle.trim,
  ].filter(Boolean);
  return parts.length > 0 ? parts.join(' ') : (vehicle.vin ?? fallback);
}

/** The vehicles a customer owns today, a likely trade-in. */
export function OwnedVehiclesCard({customer}: {customer: Customer}) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [adding, setAdding] = useState(false);
  const [removing, setRemoving] = useState<Vehicle | null>(null);
  const [error, setError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      year: '',
      make: '',
      model: '',
      trim: '',
      vin: '',
      purchased_at: '',
    },
  });
  const {isValid, isSubmitting, errors} = form.formState;

  function saved(updated: Customer) {
    queryClient.setQueryData(customersKeys.detail(customer.id), updated);
    void queryClient.invalidateQueries({queryKey: ['customers', 'list']});
  }

  const onSubmit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      saved(
        await addOwnedVehicle(customer.id, {
          year: values.year === '' ? null : Number(values.year),
          make: values.make || null,
          model: values.model || null,
          trim: values.trim || null,
          vin: values.vin || null,
          purchased_at: values.purchased_at || null,
        }),
      );
      toast.success(t('vehicles.saved'));
      form.reset();
      setAdding(false);
    } catch (e) {
      if (e instanceof ApiError && e.code === 'invalid_date') {
        form.setError('purchased_at', {message: t('error.invalid_date')});
      } else {
        setError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  async function remove(vehicle: Vehicle) {
    try {
      saved(await removeOwnedVehicle(customer.id, vehicle.id));
      toast.success(t('vehicles.removed'));
      setRemoving(null);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    }
  }

  const fieldError = (name: keyof Values) => {
    const message = errors[name]?.message;
    if (!message) return null;
    if (message === 'empty') return t('vehicles.hint');
    if (message === 'year') return t('vehicles.year_hint');
    return message;
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle>{t('vehicles.title')}</CardTitle>
        <Button variant="outline" size="sm" onClick={() => setAdding(true)}>
          <Plus /> {t('vehicles.add')}
        </Button>
      </CardHeader>
      <CardContent>
        {customer.owned_vehicles.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('vehicles.empty')}</p>
        ) : (
          <ul className="divide-y">
            {customer.owned_vehicles.map((vehicle) => (
              <li key={vehicle.id} className="flex items-center gap-2 py-2">
                <Car
                  aria-hidden
                  className="size-4 shrink-0 text-muted-foreground"
                />
                <div className="min-w-0 flex-1 text-sm">
                  <p className="truncate font-medium">
                    {title(vehicle, t('vehicles.unnamed'))}
                  </p>
                  {vehicle.vin && (
                    <p className="tabular truncate text-xs text-muted-foreground">
                      {vehicle.vin}
                    </p>
                  )}
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  aria-label={`${t('vehicles.remove')} ${title(vehicle, t('vehicles.unnamed'))}`}
                  onClick={() => setRemoving(vehicle)}
                >
                  <Trash2 />
                </Button>
              </li>
            ))}
          </ul>
        )}
        {error && !adding && (
          <p role="alert" className="mt-2 text-sm text-danger">
            {error}
          </p>
        )}
      </CardContent>

      <Dialog open={adding} onOpenChange={setAdding}>
        <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('vehicles.add_title')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={onSubmit} className="space-y-3" noValidate>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="ov_year">{t('vehicles.year')}</Label>
                <Input
                  id="ov_year"
                  inputMode="numeric"
                  {...form.register('year')}
                />
                {fieldError('year') && (
                  <p role="alert" className="text-sm text-danger">
                    {fieldError('year')}
                  </p>
                )}
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="ov_make">{t('vehicles.make')}</Label>
                <Input id="ov_make" {...form.register('make')} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="ov_model">{t('vehicles.model')}</Label>
                <Input id="ov_model" {...form.register('model')} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="ov_trim">{t('vehicles.trim')}</Label>
                <Input id="ov_trim" {...form.register('trim')} />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="ov_vin">{t('vehicles.vin')}</Label>
              <Input id="ov_vin" maxLength={17} {...form.register('vin')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="ov_purchased">{t('vehicles.purchased_at')}</Label>
              <Input
                id="ov_purchased"
                type="date"
                {...form.register('purchased_at')}
              />
              {fieldError('purchased_at') && (
                <p role="alert" className="text-sm text-danger">
                  {fieldError('purchased_at')}
                </p>
              )}
            </div>
            {fieldError('make') && (
              <p role="alert" className="text-sm text-danger">
                {fieldError('make')}
              </p>
            )}
            {error && (
              <p role="alert" className="text-sm text-danger">
                {error}
              </p>
            )}
            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setAdding(false)}
              >
                {t('common:action.cancel')}
              </Button>
              <Button type="submit" disabled={!isValid || isSubmitting}>
                {t('common:action.save')}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog
        open={removing !== null}
        onOpenChange={(next) => !next && setRemoving(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t('vehicles.remove_title', {
                vehicle: removing ? title(removing, t('vehicles.unnamed')) : '',
              })}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t('vehicles.remove_description', {name: customer.name})}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common:action.cancel')}</AlertDialogCancel>
            <Button
              variant="destructive"
              onClick={() => removing && void remove(removing)}
            >
              {t('vehicles.remove')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
