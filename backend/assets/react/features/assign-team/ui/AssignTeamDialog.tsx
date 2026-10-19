import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {Controller, useForm} from 'react-hook-form';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {assignTeam, customersKeys, type Customer} from '@/entities/customer';
import {listUsers, usersKeys} from '@/entities/user';
import {Button} from '@/shared/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog';
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
const SLOTS = [
  {slot: 'sales1', roles: ['sales', 'manager']},
  {slot: 'sales2', roles: ['sales', 'manager']},
  {slot: 'bdc', roles: ['bdc']},
] as const;
type Slot = (typeof SLOTS)[number]['slot'];
type Values = Record<Slot, string>;

interface Props {
  customer: Customer;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Sales 1, Sales 2 and BDC, each from the people who hold that role. */
export function AssignTeamDialog({customer, open, onOpenChange}: Props) {
  const {t, i18n} = useTranslation('customers');
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
    enabled: open,
  });
  const form = useForm<Values>({
    defaultValues: {
      sales1: customer.assignments.sales1?.id ?? NONE,
      sales2: customer.assignments.sales2?.id ?? NONE,
      bdc: customer.assignments.bdc?.id ?? NONE,
    },
  });
  const pick = (value: string) => (value === NONE ? null : value);

  const onSubmit = form.handleSubmit(async (values) => {
    setError(null);
    try {
      const updated = await assignTeam(customer.id, {
        sales1_user_id: pick(values.sales1),
        sales2_user_id: pick(values.sales2),
        bdc_user_id: pick(values.bdc),
      });
      queryClient.setQueryData(customersKeys.detail(customer.id), updated);
      void queryClient.invalidateQueries({queryKey: ['customers', 'list']});
      toast.success(t('team.saved'));
      onOpenChange(false);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
    }
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('team.title')}</DialogTitle>
          <DialogDescription>{t('team.description')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          {SLOTS.map(({slot, roles}) => {
            const current = customer.assignments[slot];
            const people = (users.data?.items ?? []).filter(
              (u) =>
                (u.active && roles.some((r) => r === u.role)) ||
                u.id === current?.id,
            );
            return (
              <div key={slot} className="space-y-1.5">
                <Label htmlFor={`team_${slot}`}>{t(`team.${slot}`)}</Label>
                <Controller
                  control={form.control}
                  name={slot}
                  render={({field}) => (
                    <Select value={field.value} onValueChange={field.onChange}>
                      <SelectTrigger id={`team_${slot}`} className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={NONE}>{t('team.none')}</SelectItem>
                        {current &&
                          !people.some((u) => u.id === current.id) && (
                            <SelectItem value={current.id}>
                              {current.name}
                            </SelectItem>
                          )}
                        {people.map((u) => (
                          <SelectItem key={u.id} value={u.id}>
                            {u.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                />
              </div>
            );
          })}
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
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {t('common:action.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
