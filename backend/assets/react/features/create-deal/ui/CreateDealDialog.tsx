import {useQuery, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {toast} from 'sonner';
import {customersKeys, listCustomers} from '@/entities/customer';
import {createDeal, dealsKeys} from '@/entities/deal';
import {ApiError} from '@/shared/api';
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
import {StageBadge} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';
import {errorText} from '../lib/errors';

interface CustomerRef {
  id: string;
  name: string;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Open the deal for this customer; without it the dialog searches for one. */
  customer?: CustomerRef;
}

/** Open a deal for a customer (stage New), then go to it. */
export function CreateDealDialog(props: Props) {
  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
        {/* The form is mounted on every open, so it starts from the record as it is now. */}
        <CreateDealDialogBody {...props} />
      </DialogContent>
    </Dialog>
  );
}

function CreateDealDialogBody({open, onOpenChange, customer}: Props) {
  const {t, i18n} = useTranslation('deals');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [term, setTerm] = useState('');
  const [picked, setPicked] = useState<CustomerRef | null>(customer ?? null);
  const [vehicleText, setVehicleText] = useState('');
  const [missing, setMissing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [existing, setExisting] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const query = {q: term.trim(), per_page: 6};
  const customers = useQuery({
    queryKey: customersKeys.list(query),
    queryFn: () => listCustomers(query),
    enabled: open && !picked && term.trim().length > 0,
  });

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (!picked) {
      setMissing(true);
      return;
    }
    setError(null);
    setExisting(null);
    setSaving(true);
    try {
      const deal = await createDeal({
        customer_id: picked.id,
        assigned_user_id: null,
        vehicle_id: null,
        vehicle_text: vehicleText.trim() === '' ? null : vehicleText.trim(),
        expected_gross_cents: null,
      });
      queryClient.setQueryData(dealsKeys.detail(deal.id), deal);
      void queryClient.invalidateQueries({queryKey: ['deals', 'list']});
      void queryClient.invalidateQueries({queryKey: ['customers']});
      toast.success(t('create_dialog.created', {name: deal.customer.name}));
      onOpenChange(false);
      void navigate(`/deals/${deal.id}`);
    } catch (e) {
      setError(errorText(t, (k) => i18n.exists(k), e));
      if (
        e instanceof ApiError &&
        e.code === 'open_deal_exists' &&
        typeof e.detail.deal_id === 'string'
      ) {
        setExisting(e.detail.deal_id);
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{t('create_dialog.title')}</DialogTitle>
        <DialogDescription>{t('create_dialog.description')}</DialogDescription>
      </DialogHeader>
      <form onSubmit={(e) => void submit(e)} className="space-y-4" noValidate>
        {picked ? (
          <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/50 px-3 py-2 text-sm">
            <span className="font-medium">
              {t('create_dialog.chosen', {name: picked.name})}
            </span>
            {!customer && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setPicked(null)}
              >
                {t('create_dialog.change_customer')}
              </Button>
            )}
          </div>
        ) : (
          <div className="space-y-2">
            <div className="space-y-1.5">
              <Label htmlFor="deal_customer_search">
                {t('create_dialog.customer_search')}
              </Label>
              <Input
                id="deal_customer_search"
                type="search"
                value={term}
                autoComplete="off"
                placeholder={t('create_dialog.customer_placeholder')}
                aria-invalid={missing || undefined}
                aria-describedby={missing ? 'deal_customer_error' : undefined}
                onChange={(e) => {
                  setTerm(e.target.value);
                  setMissing(false);
                }}
              />
              {missing && (
                <p id="deal_customer_error" className="text-sm text-danger">
                  {t('create_dialog.customer_required')}
                </p>
              )}
            </div>
            {term.trim() !== '' &&
              (customers.isPending ? (
                <div
                  className="space-y-2"
                  aria-busy="true"
                  aria-label={t('create_dialog.searching')}
                >
                  <Skeleton className="h-11 w-full" />
                  <Skeleton className="h-11 w-full" />
                </div>
              ) : customers.data && customers.data.items.length > 0 ? (
                <ul className="divide-y rounded-md border">
                  {customers.data.items.map((c) => (
                    <li key={c.id}>
                      <button
                        type="button"
                        className="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-muted"
                        aria-label={t('create_dialog.pick', {name: c.name})}
                        onClick={() => setPicked({id: c.id, name: c.name})}
                      >
                        <span>
                          <span className="font-medium">{c.name}</span>{' '}
                          <span className="text-muted-foreground">
                            {c.primary_phone ?? c.primary_email ?? ''}
                          </span>
                        </span>
                        {c.open_deal_stage && (
                          <StageBadge stage={c.open_deal_stage} />
                        )}
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-muted-foreground">
                  {t('create_dialog.no_customers')}
                </p>
              ))}
          </div>
        )}
        <div className="space-y-1.5">
          <Label htmlFor="deal_vehicle_text">
            {t('create_dialog.vehicle_text')}
          </Label>
          <Input
            id="deal_vehicle_text"
            value={vehicleText}
            maxLength={255}
            placeholder={t('create_dialog.vehicle_placeholder')}
            onChange={(e) => setVehicleText(e.target.value)}
          />
        </div>
        {error && (
          <div role="alert" className="space-y-1 text-sm text-danger">
            <p>{error}</p>
            {existing && (
              <Link
                to={`/deals/${existing}`}
                className="font-medium underline"
                onClick={() => onOpenChange(false)}
              >
                {t('create_dialog.open_existing')}
              </Link>
            )}
          </div>
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
            {t('create_dialog.submit')}
          </Button>
        </DialogFooter>
      </form>
    </>
  );
}
