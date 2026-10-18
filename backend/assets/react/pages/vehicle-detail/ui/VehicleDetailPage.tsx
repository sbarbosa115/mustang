import {useQuery} from '@tanstack/react-query';
import {ArrowLeft, Car, SearchX} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {fetchVehicle, vehiclesKeys, type Vehicle} from '@/entities/vehicle';
import {VehicleForm} from '@/features/add-vehicle';
import {EditVehicleDialog, VehicleStatusSelect} from '@/features/edit-vehicle';
import {ApiError} from '@/shared/api';
import {formatCents, formatMileage} from '@/shared/lib/money';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {Card, CardContent} from '@/shared/ui/card';
import {EmptyState} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';

/** One vehicle: photo, price and specs, with edit and status (FR-21). */
export function VehicleDetailPage() {
  const {t} = useTranslation('inventory');
  const {vehicleId = ''} = useParams();
  const vehicle = useQuery({
    queryKey: vehiclesKeys.detail(vehicleId),
    queryFn: () => fetchVehicle(vehicleId),
    retry: (count, error) => !(error instanceof ApiError) && count < 2,
  });

  const back = (
    <Button variant="ghost" size="sm" asChild className="-ml-2">
      <Link to="/inventory">
        <ArrowLeft aria-hidden />
        {t('detail.back')}
      </Link>
    </Button>
  );

  if (vehicle.isPending) {
    return (
      <>
        <PageHeader title={t('title')} back={back} />
        <Card aria-busy="true" aria-label={t('detail.loading')}>
          <CardContent className="space-y-3">
            <Skeleton className="aspect-[16/9] w-full" />
            <Skeleton className="h-6 w-1/2" />
            <Skeleton className="h-4 w-1/3" />
          </CardContent>
        </Card>
      </>
    );
  }
  if (vehicle.isError) {
    const missing =
      vehicle.error instanceof ApiError && vehicle.error.status === 404;
    return (
      <>
        <PageHeader title={t('title')} back={back} />
        <Card>
          <EmptyState
            icon={missing ? SearchX : Car}
            title={missing ? t('detail.not_found') : t('detail.error')}
            action={
              missing ? (
                <Button variant="outline" asChild>
                  <Link to="/inventory">{t('detail.back')}</Link>
                </Button>
              ) : (
                <Button
                  variant="outline"
                  onClick={() => void vehicle.refetch()}
                >
                  {t('detail.retry')}
                </Button>
              )
            }
          />
        </Card>
      </>
    );
  }

  const v = vehicle.data;
  return (
    <>
      <PageHeader
        title={v.title}
        description={v.trim ?? undefined}
        back={back}
        actions={
          <EditVehicleDialog
            vehicle={v}
            renderForm={(props) => <VehicleForm {...props} />}
          />
        }
      />
      <div className="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <Card className="gap-0 overflow-hidden py-0">
          <div className="flex aspect-[16/10] items-center justify-center bg-muted">
            {v.photo_url ? (
              <img
                src={v.photo_url}
                alt={v.title}
                className="size-full object-cover"
              />
            ) : (
              <Car aria-hidden className="size-16 text-muted-foreground" />
            )}
          </div>
          {v.description && (
            <CardContent className="p-4">
              <h2 className="mb-1 text-sm font-medium">
                {t('detail.description')}
              </h2>
              <p className="text-sm whitespace-pre-line text-muted-foreground">
                {v.description}
              </p>
            </CardContent>
          )}
        </Card>

        <div className="space-y-4">
          <Card>
            <CardContent className="space-y-4">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">
                  {t(`condition.${v.condition}`)}
                </Badge>
                <Badge
                  variant={v.status === 'available' ? 'secondary' : 'outline'}
                >
                  {t(`status.${v.status}`)}
                </Badge>
                <span className="font-mono text-sm text-muted-foreground">
                  {v.stock_number}
                </span>
              </div>
              <p className="tabular text-3xl font-semibold">
                {v.price_cents == null
                  ? t('price.call')
                  : formatCents(v.price_cents)}
              </p>
              <VehicleStatusSelect vehicle={v} />
            </CardContent>
          </Card>
          <Card>
            <CardContent>
              <h2 className="mb-3 text-sm font-medium">{t('detail.specs')}</h2>
              <Specs vehicle={v} />
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

function Specs({vehicle: v}: {vehicle: Vehicle}) {
  const {t} = useTranslation('inventory');
  const rows: Array<[string, string | null | undefined]> = [
    [t('form.year'), String(v.year)],
    [t('form.make'), v.make],
    [t('form.model'), v.model],
    [t('form.trim'), v.trim],
    [t('form.body'), v.body],
    [t('form.drivetrain'), v.drivetrain],
    [t('form.fuel'), v.fuel],
    [t('form.exterior_color'), v.exterior_color],
    [t('form.interior_color'), v.interior_color],
    [t('form.mileage'), v.mileage == null ? null : formatMileage(v.mileage)],
    [t('form.vin'), v.vin],
  ];
  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
      {rows
        .filter(([, value]) => value)
        .map(([label, value]) => (
          <div key={label} className="contents">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="tabular text-right font-medium">{value}</dd>
          </div>
        ))}
    </dl>
  );
}
