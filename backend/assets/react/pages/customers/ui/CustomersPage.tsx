import {Plus} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {useNavigate, useSearchParams} from 'react-router';
import {CreateCustomerDialog} from '@/features/create-customer';
import {Button} from '@/shared/ui/button';
import {PageHeader} from '@/shared/ui/page';
import {CustomerList} from '@/widgets/customer-list';

/** /customers: the list, and "Add customer" (also opened by /customers?new=1 from the top bar). */
export function CustomersPage() {
  const {t} = useTranslation('customers');
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const creating = params.get('new') === '1';

  const setCreating = (open: boolean) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        if (open) next.set('new', '1');
        else next.delete('new');
        return next;
      },
      {replace: true},
    );

  // The top bar carries the primary "Add customer" from the tablet width up; the phone has no top bar button.
  const add = (
    <Button className="sm:hidden" onClick={() => setCreating(true)}>
      <Plus /> {t('create.open')}
    </Button>
  );

  return (
    <>
      <PageHeader title={t('list.title')} actions={add} />
      <CustomerList
        emptyAction={
          <Button variant="outline" onClick={() => setCreating(true)}>
            <Plus /> {t('create.open')}
          </Button>
        }
      />
      <CreateCustomerDialog
        open={creating}
        onOpenChange={setCreating}
        onCreated={(customer) => void navigate(`/customers/${customer.id}`)}
      />
    </>
  );
}
