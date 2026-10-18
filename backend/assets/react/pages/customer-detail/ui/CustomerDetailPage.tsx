import {useQuery} from '@tanstack/react-query';
import {
  Archive,
  ArrowLeft,
  FileCheck2,
  MoreHorizontal,
  Pencil,
  Phone,
  Users,
} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useParams, useSearchParams} from 'react-router';
import {customersKeys, CustomerPanel, fetchCustomer} from '@/entities/customer';
import {NotesPanel} from '@/features/add-note';
import {AssignTeamDialog} from '@/features/assign-team';
import {
  ArchiveCustomerDialog,
  EditCustomerDialog,
} from '@/features/edit-customer';
import {
  ManageContactsDialog,
  OwnedVehiclesCard,
} from '@/features/manage-contacts';
import {ApiError} from '@/shared/api';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/ui/dropdown-menu';
import {EmptyState} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';
import {Tabs, TabsContent, TabsList, TabsTrigger} from '@/shared/ui/tabs';
import {AppointmentsTab} from './AppointmentsTab';
import {DealsTab} from './DealsTab';
import {TimelineTab} from './TimelineTab';

type Dialog = 'edit' | 'contacts' | 'team' | 'archive' | null;
const TABS = ['overview', 'timeline', 'deals', 'appointments'] as const;
type Tab = (typeof TABS)[number];

/** /customers/:customerId: the record, with Overview, Timeline, Deals and Appointments tabs. */
export function CustomerDetailPage() {
  const {t} = useTranslation('customers');
  const {customerId = ''} = useParams();
  const [params, setParams] = useSearchParams();
  const [dialog, setDialog] = useState<Dialog>(null);
  const requested = params.get('tab');
  const tab: Tab = (TABS as readonly string[]).includes(requested ?? '')
    ? (requested as Tab)
    : 'overview';
  const customer = useQuery({
    queryKey: customersKeys.detail(customerId),
    queryFn: () => fetchCustomer(customerId),
    enabled: customerId !== '',
  });

  const back = (
    <Link
      to="/customers"
      className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowLeft aria-hidden className="size-4" />
      {t('detail.back')}
    </Link>
  );

  if (customer.isLoading) {
    return (
      <div
        className="space-y-4"
        aria-busy="true"
        aria-label={t('detail.loading')}
      >
        {back}
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }
  if (customer.isError || !customer.data) {
    const notFound =
      customer.error instanceof ApiError && customer.error.status === 404;
    return (
      <div className="space-y-4">
        {back}
        <EmptyState
          icon={Users}
          title={notFound ? t('detail.not_found') : t('common:list.error')}
          action={
            notFound ? undefined : (
              <Button variant="outline" onClick={() => void customer.refetch()}>
                {t('common:action.retry')}
              </Button>
            )
          }
        />
      </div>
    );
  }

  const data = customer.data;
  const archived = data.archived_at != null;
  const setTab = (next: string) =>
    setParams(
      (prev) => {
        const p = new URLSearchParams(prev);
        if (next === 'overview') p.delete('tab');
        else p.set('tab', next);
        return p;
      },
      {replace: true},
    );

  const menu = (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="icon" aria-label={t('detail.actions')}>
          <MoreHorizontal />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onSelect={() => setDialog('edit')}>
          <Pencil /> {t('edit.action')}
        </DropdownMenuItem>
        <DropdownMenuItem onSelect={() => setDialog('contacts')}>
          <Phone /> {t('contacts.manage')}
        </DropdownMenuItem>
        <DropdownMenuItem onSelect={() => setDialog('team')}>
          <Users /> {t('team.action')}
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link to={`/customers/${data.id}/consent`}>
            <FileCheck2 /> {t('detail.consent')}
          </Link>
        </DropdownMenuItem>
        {!archived && (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              variant="destructive"
              onSelect={() => setDialog('archive')}
            >
              <Archive /> {t('archive.action')}
            </DropdownMenuItem>
          </>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );

  return (
    <>
      <PageHeader
        back={back}
        title={data.name}
        description={undefined}
        actions={
          <>
            {archived && (
              <Badge variant="secondary">{t('archive.badge')}</Badge>
            )}
            {menu}
          </>
        }
      />
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList aria-label={t('detail.tabs_label')} className="self-start">
          {TABS.map((name) => (
            <TabsTrigger key={name} value={name}>
              {t(`detail.tab.${name}`)}
            </TabsTrigger>
          ))}
        </TabsList>
        <TabsContent value="overview" className="grid gap-4 lg:grid-cols-2">
          <div className="space-y-4">
            <CustomerPanel customer={data} />
            <OwnedVehiclesCard customer={data} />
          </div>
          <NotesPanel customerId={data.id} />
        </TabsContent>
        <TabsContent value="timeline">
          <TimelineTab customerId={data.id} />
        </TabsContent>
        <TabsContent value="deals">
          <DealsTab customerId={data.id} />
        </TabsContent>
        <TabsContent value="appointments">
          <AppointmentsTab customerId={data.id} />
        </TabsContent>
      </Tabs>

      <EditCustomerDialog
        key={data.updated_at}
        customer={data}
        open={dialog === 'edit'}
        onOpenChange={(open) => setDialog(open ? 'edit' : null)}
      />
      <ManageContactsDialog
        customer={data}
        open={dialog === 'contacts'}
        onOpenChange={(open) => setDialog(open ? 'contacts' : null)}
      />
      <AssignTeamDialog
        key={`team-${data.updated_at}`}
        customer={data}
        open={dialog === 'team'}
        onOpenChange={(open) => setDialog(open ? 'team' : null)}
      />
      <ArchiveCustomerDialog
        customer={data}
        open={dialog === 'archive'}
        onOpenChange={(open) => setDialog(open ? 'archive' : null)}
        onArchived={() => undefined}
      />
    </>
  );
}
