import {zodResolver} from '@hookform/resolvers/zod';
import {useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {z} from 'zod';
import {
  customersKeys,
  updateCustomer,
  type Customer,
} from '@/entities/customer';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
import {Input} from '@/shared/ui/input';
import {Label} from '@/shared/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {errorText} from '../lib/errors';

const NONE = 'none';
const schema = z.object({
  first_name: z.string().trim().min(1).max(80),
  last_name: z.string().trim().min(1).max(80),
  preferred_channel: z.string(),
});
type Values = z.infer<typeof schema>;

interface Props {
  customer: Customer;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Name and preferred channel. Contacts, team and vehicles have their own dialogs. */
export function EditCustomerDialog({customer, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      first_name: customer.first_name,
      last_name: customer.last_name,
      preferred_channel: customer.preferred_channel ?? NONE,
    },
  });
  const {isValid, isSubmitting, errors} = form.formState;

  const onSubmit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      const updated = await updateCustomer(customer.id, {
        first_name: values.first_name.trim(),
        last_name: values.last_name.trim(),
        preferred_channel:
          values.preferred_channel === NONE
            ? null
            : (values.preferred_channel as 'text' | 'email' | 'call'),
      });
      queryClient.setQueryData(customersKeys.detail(customer.id), updated);
      void queryClient.invalidateQueries({queryKey: ['customers', 'list']});
      toast.success(t('edit.saved'));
      onOpenChange(false);
    } catch (e) {
      if (e instanceof ApiError && e.violations.length > 0) {
        for (const v of e.violations) {
          form.setError(v.field as keyof Values, {message: v.message});
        }
      } else {
        setError(errorText(t, (k) => i18n.exists(k), e));
      }
    }
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('edit.title')}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="edit_first_name">{t('edit.first_name')}</Label>
              <Input id="edit_first_name" {...form.register('first_name')} />
              {errors.first_name && (
                <p role="alert" className="text-sm text-danger">
                  {errors.first_name.message}
                </p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="edit_last_name">{t('edit.last_name')}</Label>
              <Input id="edit_last_name" {...form.register('last_name')} />
              {errors.last_name && (
                <p role="alert" className="text-sm text-danger">
                  {errors.last_name.message}
                </p>
              )}
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="edit_channel">{t('edit.preferred_channel')}</Label>
            <Controller
              control={form.control}
              name="preferred_channel"
              render={({field}) => (
                <Select value={field.value} onValueChange={field.onChange}>
                  <SelectTrigger id="edit_channel" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={NONE}>
                      {t('edit.preferred_none')}
                    </SelectItem>
                    <SelectItem value="text">
                      {t('common:channel.text')}
                    </SelectItem>
                    <SelectItem value="email">
                      {t('common:channel.email')}
                    </SelectItem>
                    <SelectItem value="call">
                      {t('common:channel.call')}
                    </SelectItem>
                  </SelectContent>
                </Select>
              )}
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
            <Button type="submit" disabled={!isValid || isSubmitting}>
              {t('common:action.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
