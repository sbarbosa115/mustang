import {useQuery} from '@tanstack/react-query';
import {Car, Search, User} from 'lucide-react';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {customersKeys, listCustomers} from '@/entities/customer';
import {listVehicles, vehiclesKeys} from '@/entities/vehicle';
import {formatPhone} from '@/shared/lib/phone';
import {Button} from '@/shared/ui/button';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/shared/ui/command';

function useDebounced(value: string, ms = 200): string {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(value), ms);
    return () => window.clearTimeout(timer);
  }, [value, ms]);
  return debounced;
}

/** ⌘K / Ctrl K: customers by name, phone or email, and vehicles by stock number or model (DESIGN.md). */
export function QuickSearch() {
  const {t} = useTranslation('shell');
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [term, setTerm] = useState('');
  const q = useDebounced(term.trim());
  const enabled = open && q.length >= 2;

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        setOpen((value) => !value);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const customers = useQuery({
    queryKey: customersKeys.list({q, per_page: 5}),
    queryFn: () => listCustomers({q, per_page: 5}),
    enabled,
  });
  // Inventory may not be built yet; a failed vehicle search simply shows no vehicles.
  const vehicles = useQuery({
    queryKey: vehiclesKeys.list({q, per_page: 5}),
    queryFn: () => listVehicles({q, per_page: 5}),
    enabled,
    retry: false,
  });

  const go = (to: string) => {
    setOpen(false);
    setTerm('');
    navigate(to);
  };

  return (
    <>
      <Button
        variant="outline"
        className="w-full justify-start text-muted-foreground sm:w-72"
        onClick={() => setOpen(true)}
        aria-label={t('search.open')}
      >
        <Search aria-hidden />
        <span className="truncate">{t('search.open')}</span>
        <kbd className="ml-auto hidden rounded border px-1.5 text-xs lg:inline">
          {t('search.shortcut')}
        </kbd>
      </Button>
      <CommandDialog
        shouldFilter={false}
        open={open}
        onOpenChange={setOpen}
        title={t('search.open')}
        description={t('search.placeholder')}
      >
        <CommandInput
          placeholder={t('search.placeholder')}
          value={term}
          onValueChange={setTerm}
        />
        <CommandList>
          {!enabled ? (
            <p className="p-4 text-sm text-muted-foreground">
              {t('search.hint')}
            </p>
          ) : (
            <CommandEmpty>{t('search.empty')}</CommandEmpty>
          )}
          {enabled && (customers.data?.items.length ?? 0) > 0 && (
            <CommandGroup heading={t('search.customers')}>
              {customers.data?.items.map((customer) => (
                <CommandItem
                  key={customer.id}
                  value={`customer-${customer.id}`}
                  onSelect={() => go(`/customers/${customer.id}`)}
                >
                  <User aria-hidden />
                  <span>{customer.name}</span>
                  <span className="ml-auto text-xs text-muted-foreground">
                    {formatPhone(customer.primary_phone) ||
                      customer.primary_email}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          )}
          {enabled && (vehicles.data?.items.length ?? 0) > 0 && (
            <CommandGroup heading={t('search.vehicles')}>
              {vehicles.data?.items.map((vehicle) => (
                <CommandItem
                  key={vehicle.id}
                  value={`vehicle-${vehicle.id}`}
                  onSelect={() => go(`/inventory/${vehicle.id}`)}
                >
                  <Car aria-hidden />
                  <span>{vehicle.title}</span>
                  <span className="ml-auto font-mono text-xs text-muted-foreground">
                    {vehicle.stock_number}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          )}
        </CommandList>
      </CommandDialog>
    </>
  );
}
