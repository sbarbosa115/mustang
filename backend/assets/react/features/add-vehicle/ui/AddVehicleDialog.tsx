import {useMutation, useQueryClient} from '@tanstack/react-query';
import {Plus} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {createVehicle, vehiclesKeys} from '@/entities/vehicle';
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
import type {VehicleInput} from '../model/form';
import {VehicleForm} from './VehicleForm';

interface Props {
  /** Called with the new vehicle's id, e.g. to open it. */
  onAdded?: (id: string) => void;
}

/** "Add vehicle": the form in a dialog, the new vehicle appears in the list when it is saved. */
export function AddVehicleDialog({onAdded}: Props) {
  const {t} = useTranslation('inventory');
  const [open, setOpen] = useState(false);
  const queryClient = useQueryClient();
  const add = useMutation({
    mutationFn: (input: VehicleInput) => createVehicle(input),
    onSuccess: async (vehicle) => {
      await queryClient.invalidateQueries({queryKey: vehiclesKeys.all});
      toast.success(t('add.done', {title: vehicle.title}));
      setOpen(false);
      onAdded?.(vehicle.id);
    },
  });

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) add.reset();
      }}
    >
      <DialogTrigger asChild>
        <Button>
          <Plus aria-hidden />
          {t('add.button')}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('add.title')}</DialogTitle>
          <DialogDescription>{t('add.description')}</DialogDescription>
        </DialogHeader>
        <VehicleForm
          onSubmit={(input) => add.mutate(input)}
          submitting={add.isPending}
          error={add.error instanceof ApiError ? add.error : null}
          onCancel={() => setOpen(false)}
          submitLabel={t('add.submit')}
        />
      </DialogContent>
    </Dialog>
  );
}
