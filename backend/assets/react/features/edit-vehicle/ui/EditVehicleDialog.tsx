import {useMutation, useQueryClient} from '@tanstack/react-query';
import {Pencil} from 'lucide-react';
import {useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
  updateVehicle,
  vehiclesKeys,
  type Vehicle,
  type VehicleInput,
} from '@/entities/vehicle';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/shared/ui/dialog';

export interface EditFormProps {
  vehicle: Vehicle;
  onSubmit: (input: VehicleInput) => void;
  submitting: boolean;
  error: ApiError | null;
  onCancel: () => void;
}

interface Props {
  vehicle: Vehicle;
  /**
   * The form to show. A feature cannot import its sibling add-vehicle, so the page that composes both passes
   * `VehicleForm` in.
   */
  renderForm: (props: EditFormProps) => ReactNode;
}

/** "Edit": the vehicle's form in a dialog. The stock number does not change; an empty text clears a field. */
export function EditVehicleDialog({vehicle, renderForm}: Props) {
  const {t} = useTranslation('inventory');
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const save = useMutation({
    mutationFn: ({stock_number: _stock, ...changes}: VehicleInput) =>
      updateVehicle(vehicle.id, changes),
    onSuccess: async (updated) => {
      queryClient.setQueryData(vehiclesKeys.detail(vehicle.id), updated);
      await queryClient.invalidateQueries({queryKey: vehiclesKeys.all});
      toast.success(t('edit.done', {title: updated.title}));
      setOpen(false);
    },
  });

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) save.reset();
      }}
    >
      <DialogTrigger asChild>
        <Button variant="outline">
          <Pencil aria-hidden />
          {t('edit.button')}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('edit.title', {title: vehicle.title})}</DialogTitle>
          <DialogDescription>{t('edit.description')}</DialogDescription>
        </DialogHeader>
        {renderForm({
          vehicle,
          onSubmit: (input) => save.mutate(input),
          submitting: save.isPending,
          error: save.error instanceof ApiError ? save.error : null,
          onCancel: () => setOpen(false),
        })}
      </DialogContent>
    </Dialog>
  );
}
