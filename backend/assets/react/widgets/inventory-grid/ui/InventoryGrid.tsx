import {keepPreviousData, useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {Car} from 'lucide-react';
import {useCallback, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useSearchParams} from 'react-router';
import {
  fetchVehicleFacets,
  listVehicles,
  vehiclesKeys,
  type Vehicle,
} from '@/entities/vehicle';
import {
  EMPTY_FILTERS,
  InventoryFilters,
  filtersFromParams,
  filtersToParams,
  isFiltered,
  toApiQuery,
  type InventoryFilterValues,
} from '@/features/filter-inventory';
import {formatCents, formatMileage} from '@/shared/lib/money';
import {Badge} from '@/shared/ui/badge';
import {DataTable, Pagination, VehicleCard} from '@/shared/ui/mustang';

const PER_PAGE = 25;

/**
 * The inventory as a table (cards on a phone) with its filters. Filters, sort and page live in the URL, so a view can
 * be shared and the back button works.
 */
export function InventoryGrid() {
  const {t} = useTranslation('inventory');
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const filters = useMemo(() => filtersFromParams(params), [params]);
  const page = Math.max(1, Number.parseInt(params.get('page') ?? '1', 10) || 1);

  const apply = useCallback(
    (next: InventoryFilterValues, nextPage = 1) =>
      setParams(filtersToParams(next, nextPage), {replace: true}),
    [setParams],
  );

  const query = toApiQuery(filters, page, PER_PAGE);
  const list = useQuery({
    queryKey: vehiclesKeys.list(query),
    queryFn: () => listVehicles(query),
    placeholderData: keepPreviousData,
  });
  const facets = useQuery({
    queryKey: [...vehiclesKeys.all, 'facets'],
    queryFn: fetchVehicleFacets,
  });

  const columns = useMemo<Array<ColumnDef<Vehicle, unknown>>>(
    () => [
      {
        id: 'vehicle',
        header: t('columns.vehicle'),
        cell: ({row}) => (
          <Link
            to={`/inventory/${row.original.id}`}
            className="font-medium hover:underline"
            onClick={(event) => event.stopPropagation()}
          >
            {row.original.title}
            {row.original.trim && (
              <span className="ml-1 font-normal text-muted-foreground">
                {row.original.trim}
              </span>
            )}
          </Link>
        ),
      },
      {
        id: 'stock',
        header: t('columns.stock'),
        cell: ({row}) => (
          <span className="font-mono text-sm">{row.original.stock_number}</span>
        ),
      },
      {
        id: 'condition',
        header: t('columns.condition'),
        cell: ({row}) => (
          <Badge variant="secondary">
            {t(`condition.${row.original.condition}`)}
          </Badge>
        ),
      },
      {
        id: 'status',
        header: t('columns.status'),
        cell: ({row}) => (
          <Badge
            variant={
              row.original.status === 'available' ? 'secondary' : 'outline'
            }
          >
            {t(`status.${row.original.status}`)}
          </Badge>
        ),
      },
      {
        id: 'mileage',
        header: t('columns.mileage'),
        cell: ({row}) => (
          <span className="tabular">{formatMileage(row.original.mileage)}</span>
        ),
      },
      {
        id: 'price',
        header: t('columns.price'),
        cell: ({row}) => (
          <span className="tabular font-medium">
            {row.original.price_cents == null
              ? t('price.call')
              : formatCents(row.original.price_cents)}
          </span>
        ),
      },
    ],
    [t],
  );

  const data = list.data;
  return (
    <div className="space-y-4">
      <InventoryFilters
        filters={filters}
        facets={facets.data}
        onChange={(next) => apply(next)}
        onClear={() => apply({...EMPTY_FILTERS, sort: filters.sort})}
      />
      <DataTable
        columns={columns}
        data={data?.items}
        isLoading={list.isPending}
        error={list.error}
        onRetry={() => void list.refetch()}
        filtered={isFiltered(filters)}
        onClearFilters={() => apply({...EMPTY_FILTERS, sort: filters.sort})}
        empty={{icon: Car, title: t('list.empty')}}
        getRowId={(vehicle) => vehicle.id}
        onRowClick={(vehicle) => navigate(`/inventory/${vehicle.id}`)}
        renderCard={(vehicle) => (
          <Link
            to={`/inventory/${vehicle.id}`}
            className="block rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
          >
            <VehicleCard
              title={vehicle.title}
              trim={vehicle.trim}
              stockNumber={vehicle.stock_number}
              mileage={vehicle.mileage}
              priceCents={vehicle.price_cents}
              photoUrl={vehicle.photo_url}
              condition={vehicle.condition}
              status={vehicle.status}
            />
          </Link>
        )}
        footer={
          data && (
            <Pagination
              page={page}
              perPage={PER_PAGE}
              total={data.total}
              onPage={(next) => apply(filters, next)}
            />
          )
        }
      />
    </div>
  );
}
