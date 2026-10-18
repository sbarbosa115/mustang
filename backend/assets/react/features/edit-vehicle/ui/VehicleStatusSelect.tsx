import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
  changeVehicleStatus,
  vehiclesKeys,
  type Vehicle,
} from '@/entities/vehicle';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';

const STATUSES = ['available', 'hold', 'sold', 'in_transit'] as const;

/** Available, on hold, sold or in transit: saved the moment it is picked. */
export function VehicleStatusSelect({vehicle}: {vehicle: Vehicle}) {
  const {t} = useTranslation('inventory');
  const queryClient = useQueryClient();
  const change = useMutation({
    mutationFn: (status: Vehicle['status']) =>
      changeVehicleStatus(vehicle.id, {status}),
    onSuccess: async (updated) => {
      queryClient.setQueryData(vehiclesKeys.detail(vehicle.id), updated);
      await queryClient.invalidateQueries({queryKey: vehiclesKeys.all});
      toast.success(
        t('status.changed', {status: t(`status.${updated.status}`)}),
      );
    },
    onError: () => toast.error(t('status.failed')),
  });

  return (
    <div className="space-y-1.5">
      <Label htmlFor="vehicle-status">{t('status.label')}</Label>
      <Select
        value={vehicle.status}
        disabled={change.isPending}
        onValueChange={(status) => change.mutate(status as Vehicle['status'])}
      >
        <SelectTrigger
          id="vehicle-status"
          className="h-11 w-full sm:h-9 sm:w-48"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {STATUSES.map((status) => (
            <SelectItem key={status} value={status}>
              {t(`status.${status}`)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
