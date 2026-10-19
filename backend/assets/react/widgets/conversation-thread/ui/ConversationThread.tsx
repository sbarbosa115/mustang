import {useQuery, useQueryClient} from '@tanstack/react-query';
import {AlertTriangle, ArrowLeft, UserRound} from 'lucide-react';
import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useSearchParams} from 'react-router';
import {appointmentsKeys, fetchAppointment} from '@/entities/appointment';
import {conversationsKeys, markConversationRead} from '@/entities/conversation';
import {useAuth} from '@/entities/user';
import {PickTemplateButton} from '@/features/pick-template';
import {Composer} from '@/features/send-message';
import {SendVehicleButton} from '@/features/send-vehicle';
import {SimulateInbound} from '@/features/simulate-inbound';
import {ApiError} from '@/shared/api';
import {formatAppointmentTime} from '@/shared/lib/dates';
import {formatPhone} from '@/shared/lib/phone';
import {Button} from '@/shared/ui/button';
import {Card} from '@/shared/ui/card';
import {ChannelActions, EmptyState} from '@/shared/ui/mustang';
import {Skeleton} from '@/shared/ui/skeleton';
import {useConversationDetail, useCustomer} from '../model/queries';
import {ApplyTemplateFromUrl} from './ApplyTemplateFromUrl';
import {MessageList} from './MessageList';

interface Props {
  conversationId: string;
  /** Where "back" goes on a phone (the list, with its filters). */
  backHref?: string;
}

const DEFAULT_TIMEZONE = 'America/New_York';

/** One conversation: who, the messages by day, and the composer with templates and vehicles (FR-17). */
export function ConversationThread({
  conversationId,
  backHref = '/inbox',
}: Props) {
  const {t} = useTranslation('inbox');
  const {me} = useAuth();
  const queryClient = useQueryClient();
  const detail = useConversationDetail(conversationId);
  const conversation = detail.data?.conversation;
  const customer = useCustomer(conversation?.customer.id);
  const unread = conversation?.unread_count ?? 0;
  const timeZone = me?.dealership.timezone ?? DEFAULT_TIMEZONE;

  // ?appointment=<id> (from "Send confirmation"): its time fills {appointment_time}.
  const [urlParams] = useSearchParams();
  const appointmentId = urlParams.get('appointment');
  const appointment = useQuery({
    queryKey: appointmentsKeys.detail(appointmentId ?? ''),
    queryFn: () => fetchAppointment(appointmentId ?? ''),
    enabled: appointmentId !== null,
  });

  useEffect(() => {
    if (unread === 0) return;
    void markConversationRead(conversationId).then(() =>
      queryClient.invalidateQueries({queryKey: conversationsKeys.all}),
    );
  }, [conversationId, unread, queryClient]);

  const back = (
    <Button asChild variant="ghost" size="icon" className="lg:hidden">
      <Link to={backHref} aria-label={t('thread.back')}>
        <ArrowLeft aria-hidden />
      </Link>
    </Button>
  );

  if (detail.isPending) {
    return (
      <Card
        className="flex h-full flex-col gap-3 p-4"
        aria-busy="true"
        aria-label={t('thread.loading')}
      >
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-16 w-2/3" />
        <Skeleton className="ml-auto h-16 w-2/3" />
        <Skeleton className="h-16 w-1/2" />
      </Card>
    );
  }
  if (detail.isError || !detail.data || !conversation) {
    const notFound =
      detail.error instanceof ApiError && detail.error.status === 404;
    return (
      <Card className="h-full">
        <div className="p-2">{back}</div>
        <EmptyState
          icon={AlertTriangle}
          title={notFound ? t('thread.not_found') : t('thread.error')}
          action={
            !notFound && (
              <Button variant="outline" onClick={() => void detail.refetch()}>
                {t('common:action.retry')}
              </Button>
            )
          }
        />
      </Card>
    );
  }

  const {contacts, messages} = detail.data;
  const c = customer.data;
  const values = {
    first_name: c?.first_name,
    last_name: c?.last_name,
    rep_name: me?.user.name,
    dealership: me?.dealership.name,
    vehicle: c?.open_deal?.vehicle_text,
    appointment_time: appointment.data
      ? formatAppointmentTime(appointment.data.scheduled_at, timeZone)
      : undefined,
  };
  const valuesReady =
    !customer.isPending && (appointmentId === null || !appointment.isPending);

  return (
    <Card className="flex h-full min-h-0 flex-col gap-0 overflow-hidden py-0">
      <header className="flex items-center gap-2 border-b p-2 sm:p-3">
        {back}
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-lg leading-7 font-semibold">
            {conversation.customer.name}
          </h2>
          <p className="truncate text-xs text-muted-foreground">
            {[
              contacts.phone ? formatPhone(contacts.phone) : null,
              contacts.email,
            ]
              .filter(Boolean)
              .join(' · ')}
          </p>
        </div>
        <SimulateInbound contacts={contacts} />
        <ChannelActions
          availability={conversation.consent}
          channels={['call']}
          phone={contacts.phone}
          onSelect={() => undefined}
        />
        <Button asChild variant="ghost" size="icon" className="xl:hidden">
          <Link
            to={`/customers/${conversation.customer.id}`}
            aria-label={t('thread.open_customer')}
          >
            <UserRound aria-hidden />
          </Link>
        </Button>
      </header>
      <div className="min-h-0 flex-1 overflow-y-auto">
        <MessageList messages={messages} timeZone={timeZone} />
      </div>
      <Composer
        key={conversationId}
        conversationId={conversationId}
        customerId={conversation.customer.id}
        consent={conversation.consent}
        contacts={contacts}
        preferredChannel={c?.preferred_channel}
        values={values}
        tools={({channel, disabled, insert}) => (
          <>
            <ApplyTemplateFromUrl
              channel={channel}
              disabled={disabled}
              ready={valuesReady}
              insert={insert}
            />
            <PickTemplateButton
              channel={channel}
              disabled={disabled}
              onPick={(template) =>
                insert({
                  body: template.body,
                  subject: template.subject,
                  templateId: template.id,
                })
              }
            />
            <SendVehicleButton
              channel={channel}
              disabled={disabled}
              onAdd={insert}
            />
          </>
        )}
      />
    </Card>
  );
}
