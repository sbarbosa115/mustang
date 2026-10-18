import {useTranslation} from 'react-i18next';
import {AppointmentChip} from '@/entities/appointment';
import type {Schema} from '@/shared/api';
import {
  ChannelActions,
  PriorityItem,
  StageBadge,
  type Channel,
} from '@/shared/ui/mustang';
import {reasonText} from '../lib/reason';

type Item = Schema<'WorklistItemOutput'>;

interface Props {
  item: Item;
  timeZone?: string;
  onContact: (item: Item, channel: Channel) => void;
}

/** One customer to contact: who, why now (worded from the reason code), the deal and appointment, one-tap channels. */
export function ContactNowRow({item, timeZone, onContact}: Props) {
  const {t} = useTranslation('today');
  return (
    <PriorityItem
      customerName={item.customer.name}
      customerHref={`/customers/${item.customer.id}`}
      reason={reasonText(t, item, timeZone)}
      hot={item.hot}
      since={item.since}
      timeZone={timeZone}
      meta={
        <>
          {item.deal && (
            <StageBadge
              stage={item.deal.stage}
              daysInStage={item.deal.days_in_stage}
            />
          )}
          {item.appointment && (
            <AppointmentChip
              appointment={item.appointment}
              timeZone={timeZone}
            />
          )}
        </>
      }
      actions={
        <ChannelActions
          availability={item.channel_availability}
          phone={item.phone}
          suggested={item.suggested_channel}
          onSelect={(channel) => onContact(item, channel)}
        />
      }
    />
  );
}
