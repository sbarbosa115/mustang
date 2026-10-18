import {useQuery} from '@tanstack/react-query';
import type {ColumnDef} from '@tanstack/react-table';
import {ArrowLeft, Ban, FileCheck2, Plus} from 'lucide-react';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {consentsKeys, listConsents, type Consent} from '@/entities/consent';
import {customersKeys, fetchCustomer} from '@/entities/customer';
import {useAuth} from '@/entities/user';
import {RecordConsentDialog} from '@/features/record-consent';
import {RevokeConsentDialog} from '@/features/revoke-consent';
import {ApiError} from '@/shared/api';
import {formatPhone} from '@/shared/lib/phone';
import {Badge} from '@/shared/ui/badge';
import {Button} from '@/shared/ui/button';
import {Card, CardContent, CardHeader, CardTitle} from '@/shared/ui/card';
import {
  ChannelIcon,
  ConsentIndicator,
  DataTable,
  EmptyState,
  RelativeTime,
  type Channel,
} from '@/shared/ui/mustang';
import {PageHeader} from '@/shared/ui/page';
import {Skeleton} from '@/shared/ui/skeleton';

const CHANNELS: Channel[] = ['text', 'email', 'call'];

/** /customers/:customerId/consent: the state per channel, the ledger, and recording or revoking consent. */
export function CustomerConsentPage() {
  const {t, i18n} = useTranslation('consent');
  const {customerId = ''} = useParams();
  const {me} = useAuth();
  const timeZone = me?.dealership.timezone;
  const [recording, setRecording] = useState(false);
  const [revoking, setRevoking] = useState<Consent | null>(null);
  const customer = useQuery({
    queryKey: customersKeys.detail(customerId),
    queryFn: () => fetchCustomer(customerId),
    enabled: customerId !== '',
  });
  const ledger = useQuery({
    queryKey: consentsKeys.list({customerId}),
    queryFn: () => listConsents(customerId),
    enabled: customerId !== '',
  });

  if (customer.isLoading) {
    return (
      <div
        className="space-y-4"
        aria-busy="true"
        aria-label={t('page.loading')}
      >
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }
  if (customer.isError || !customer.data) {
    const notFound =
      customer.error instanceof ApiError && customer.error.status === 404;
    return (
      <EmptyState
        icon={FileCheck2}
        title={notFound ? t('page.not_found') : t('common:list.error')}
        action={
          notFound ? undefined : (
            <Button variant="outline" onClick={() => void customer.refetch()}>
              {t('common:action.retry')}
            </Button>
          )
        }
      />
    );
  }

  const data = customer.data;
  const summary = ledger.data?.summary ?? data.consent;
  const blocked = CHANNELS.filter((c) => summary[c] === 'opted_out');
  const contact = (row: Consent) =>
    row.channel === 'email'
      ? row.contact_value
      : formatPhone(row.contact_value);
  const label = (prefix: string, value: string | null) =>
    value && i18n.exists(`consent:${prefix}.${value}`)
      ? t(`${prefix}.${value}`)
      : value;

  const columns: Array<ColumnDef<Consent, unknown>> = [
    {
      id: 'channel',
      header: t('column.channel'),
      cell: ({row}) => (
        <span className="flex items-center gap-2">
          <ChannelIcon channel={row.original.channel} />
          {t(`common:channel.${row.original.channel}`)}
        </span>
      ),
    },
    {
      id: 'contact',
      header: t('column.contact'),
      cell: ({row}) => <span className="tabular">{contact(row.original)}</span>,
    },
    {
      id: 'state',
      header: t('column.state'),
      cell: ({row}) => <StateBadge consent={row.original} />,
    },
    {
      id: 'source',
      header: t('column.source'),
      cell: ({row}) => label('source', row.original.source),
    },
    {
      id: 'captured',
      header: t('column.captured'),
      cell: ({row}) => <Captured consent={row.original} timeZone={timeZone} />,
    },
    {
      id: 'details',
      header: t('column.details'),
      cell: ({row}) => <Details consent={row.original} />,
    },
    {
      id: 'actions',
      header: () => <span className="sr-only">{t('column.actions')}</span>,
      cell: ({row}) =>
        row.original.state === 'granted' ? (
          <Button
            variant="outline"
            size="sm"
            aria-label={t('revoke.action_label', {
              channel: t(`common:channel.${row.original.channel}`),
              contact: contact(row.original),
            })}
            onClick={() => setRevoking(row.original)}
          >
            {t('revoke.action')}
          </Button>
        ) : null,
    },
  ];

  return (
    <>
      <PageHeader
        back={
          <Link
            to={`/customers/${data.id}`}
            className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft aria-hidden className="size-4" />
            {t('page.back', {name: data.name})}
          </Link>
        }
        title={t('page.title', {name: data.name})}
        description={t('page.description')}
        actions={
          <Button onClick={() => setRecording(true)}>
            <Plus aria-hidden /> {t('record.action')}
          </Button>
        }
      />
      <div className="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('page.summary')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <ConsentIndicator availability={summary} />
            {blocked.map((c) => (
              <p
                key={c}
                className="flex items-start gap-2 text-sm text-muted-foreground"
              >
                <Ban
                  aria-hidden
                  className="mt-0.5 size-4 shrink-0 text-danger"
                />
                {t('page.opted_out_note', {channel: t(`common:channel.${c}`)})}
              </p>
            ))}
          </CardContent>
        </Card>
        <section aria-labelledby="consent_history" className="space-y-2">
          <h2 id="consent_history" className="text-lg font-semibold">
            {t('page.history')}
          </h2>
          <DataTable
            columns={columns}
            data={ledger.data?.items}
            isLoading={ledger.isLoading}
            error={ledger.error}
            onRetry={() => void ledger.refetch()}
            getRowId={(row) => row.id}
            empty={{
              icon: FileCheck2,
              title: t('page.empty'),
              action: (
                <Button variant="outline" onClick={() => setRecording(true)}>
                  {t('record.action')}
                </Button>
              ),
            }}
            renderCard={(row) => (
              <Card className="gap-2 p-4">
                <div className="flex items-center justify-between gap-2">
                  <span className="flex items-center gap-2 font-medium">
                    <ChannelIcon channel={row.channel} />
                    {t(`common:channel.${row.channel}`)}
                    <span className="tabular font-normal">{contact(row)}</span>
                  </span>
                  <StateBadge consent={row} />
                </div>
                <p className="text-sm text-muted-foreground">
                  {label('source', row.source)} ·{' '}
                  <Captured consent={row} timeZone={timeZone} />
                </p>
                <Details consent={row} />
                {row.state === 'granted' && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="self-start"
                    aria-label={t('revoke.action_label', {
                      channel: t(`common:channel.${row.channel}`),
                      contact: contact(row),
                    })}
                    onClick={() => setRevoking(row)}
                  >
                    {t('revoke.action')}
                  </Button>
                )}
              </Card>
            )}
          />
        </section>
      </div>
      <RecordConsentDialog
        customer={data}
        timeZone={timeZone}
        open={recording}
        onOpenChange={setRecording}
      />
      <RevokeConsentDialog
        consent={revoking}
        onOpenChange={(open) => !open && setRevoking(null)}
      />
    </>
  );
}

function StateBadge({consent}: {consent: Consent}) {
  const {t} = useTranslation('consent');
  return consent.state === 'granted' ? (
    <Badge className="bg-success-surface text-success">
      {t('state.granted')}
    </Badge>
  ) : (
    <Badge className="bg-danger-surface text-danger">
      {t('state.revoked')}
    </Badge>
  );
}

function Captured({consent, timeZone}: {consent: Consent; timeZone?: string}) {
  const {t} = useTranslation('consent');
  return (
    <span className="text-sm">
      <RelativeTime iso={consent.captured_at} timeZone={timeZone} />{' '}
      <span className="text-muted-foreground">
        {consent.captured_by
          ? t('captured_by', {name: consent.captured_by.name})
          : consent.source === 'sms_keyword'
            ? t('captured_by_customer')
            : null}
      </span>
    </span>
  );
}

function Details({consent}: {consent: Consent}) {
  const {t, i18n} = useTranslation('consent');
  const reason = consent.revoked_reason;
  const lines: Array<[string, string]> = [];
  if (consent.captured_where) lines.push([t('where'), consent.captured_where]);
  if (consent.wording) lines.push([t('wording'), consent.wording]);
  if (consent.campaign) lines.push([t('campaign'), consent.campaign]);
  if (consent.revoked_at) {
    lines.push([
      t('revoked_on'),
      reason && i18n.exists(`consent:revoked_reason.${reason}`)
        ? t(`revoked_reason.${reason}`)
        : (reason ?? ''),
    ]);
  }
  if (lines.length === 0) return null;
  return (
    <dl className="max-w-md space-y-0.5 text-sm">
      {lines.map(([term, value]) => (
        <div key={term} className="flex gap-1">
          <dt className="shrink-0 text-muted-foreground">{term}:</dt>
          <dd className="break-words">{value}</dd>
        </div>
      ))}
    </dl>
  );
}
