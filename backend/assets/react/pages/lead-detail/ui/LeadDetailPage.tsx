import {useQuery} from '@tanstack/react-query';
import {ArrowLeft, Inbox, Plus, XCircle} from 'lucide-react';
import {useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate, useParams} from 'react-router';
import {fetchLead, leadsKeys} from '@/entities/lead';
import {AssignLeadControl} from '@/features/assign-lead';
import {CaptureLeadDialog} from '@/features/capture-lead';
import {CloseLeadDialog} from '@/features/close-lead';
import {ApiError} from '@/shared/api';
import {Button} from '@/shared/ui/button';
import {Card, CardContent} from '@/shared/ui/card';
import {EmptyState, RelativeTime} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';
import {LeadStatusBadge} from '@/widgets/lead-list';

/** /leads/:leadId: where the lead came from, who has it, and handing it on or closing it. */
export function LeadDetailPage() {
  const {t} = useTranslation('leads');
  const {leadId = ''} = useParams();
  const navigate = useNavigate();
  const [closing, setClosing] = useState(false);
  const [capturing, setCapturing] = useState(false);
  const lead = useQuery({
    queryKey: leadsKeys.detail(leadId),
    queryFn: () => fetchLead(leadId),
    enabled: leadId !== '',
  });

  const back = (
    <Link
      to="/leads"
      className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowLeft aria-hidden className="size-4" />
      {t('detail.back')}
    </Link>
  );

  if (lead.isLoading) {
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
  if (lead.isError || !lead.data) {
    const notFound =
      lead.error instanceof ApiError && lead.error.status === 404;
    return (
      <div className="space-y-4">
        {back}
        <EmptyState
          icon={Inbox}
          title={notFound ? t('detail.not_found') : t('common:list.error')}
          action={
            notFound ? undefined : (
              <Button variant="outline" onClick={() => void lead.refetch()}>
                {t('common:action.retry')}
              </Button>
            )
          }
        />
      </div>
    );
  }

  const data = lead.data;
  const closed = data.status === 'closed';
  const actions = (
    <>
      {!closed && (
        <Button variant="outline" onClick={() => setClosing(true)}>
          <XCircle /> {t('close.action')}
        </Button>
      )}
      <Button variant="outline" onClick={() => setCapturing(true)}>
        <Plus /> {t('capture.open')}
      </Button>
    </>
  );

  return (
    <>
      <PageHeader
        back={back}
        title={t('detail.title', {name: data.customer.name})}
        actions={actions}
      />
      <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardContent>
            <dl className="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
              <Item label={t('detail.customer')}>
                <Link
                  to={`/customers/${data.customer.id}`}
                  className="font-medium underline-offset-4 hover:underline"
                >
                  {data.customer.name}
                </Link>
              </Item>
              <Item label={t('detail.status')}>
                <LeadStatusBadge status={data.status} />
              </Item>
              <Item label={t('detail.source')}>
                {t(`source.${data.source}`)}
              </Item>
              <Item label={t('detail.received')}>
                <RelativeTime iso={data.received_at} />
              </Item>
              <Item label={t('detail.vehicle')}>
                {data.vehicle_interest_text ?? (
                  <span className="text-muted-foreground">
                    {t('list.no_vehicle')}
                  </span>
                )}
              </Item>
              <Item label={t('detail.first_contacted')}>
                {data.first_contacted_at ? (
                  <RelativeTime iso={data.first_contacted_at} />
                ) : (
                  <span className="text-muted-foreground">
                    {t('detail.not_contacted')}
                  </span>
                )}
              </Item>
              {data.channel_note && (
                <Item label={t('detail.note')}>{data.channel_note}</Item>
              )}
              <Item label={t('detail.deal')}>
                {data.deal_id ? (
                  <Link
                    to={`/deals/${data.deal_id}`}
                    className="font-medium underline-offset-4 hover:underline"
                  >
                    {t('detail.open_deal')}
                  </Link>
                ) : (
                  <span className="text-muted-foreground">
                    {t('detail.no_deal')}
                  </span>
                )}
              </Item>
            </dl>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="space-y-4">
            <dl className="text-sm">
              <Item label={t('detail.assignee')}>
                {data.assignee?.name ?? (
                  <span className="text-muted-foreground">
                    {t('list.unassigned')}
                  </span>
                )}
              </Item>
            </dl>
            {/* Remounted per assignee so the picker starts from the current rep. */}
            <AssignLeadControl key={data.assignee?.id ?? 'none'} lead={data} />
          </CardContent>
        </Card>
      </div>
      <CloseLeadDialog lead={data} open={closing} onOpenChange={setClosing} />
      <CaptureLeadDialog
        open={capturing}
        onOpenChange={setCapturing}
        onCaptured={(next) => {
          setCapturing(false);
          void navigate(`/leads/${next.id}`);
        }}
      />
    </>
  );
}

function Item({label, children}: {label: string; children: ReactNode}) {
  return (
    <div className="space-y-1">
      <dt className="text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  );
}
