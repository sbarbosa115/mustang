import {useTranslation} from 'react-i18next';
import {Button} from '../button';
import {Tooltip, TooltipContent, TooltipTrigger} from '../tooltip';
import type {Channel, ChannelAvailability} from './channel';
import {ChannelIcon} from './ChannelIcon';

interface Props {
  availability: ChannelAvailability;
  /** Called with the channel the user tapped. A call opens the phone's dialer when `phone` is given. */
  onSelect: (channel: Channel) => void;
  phone?: string | null;
  channels?: Channel[];
  /** The channel to emphasise (the suggested one). */
  suggested?: Channel | null;
}

/**
 * One-tap call, text and email. A channel without granted consent is disabled, and its tooltip says why
 * (DESIGN.md "Mustang components"; FR-11).
 */
export function ChannelActions({
  availability,
  onSelect,
  phone,
  channels = ['call', 'text', 'email'],
  suggested,
}: Props) {
  const {t} = useTranslation('common');
  return (
    <div
      className="flex items-center gap-1"
      role="group"
      aria-label={t('channel.actions')}
    >
      {channels.map((channel) => {
        const state = availability[channel];
        const allowed = state === 'granted';
        const label = t(`channel.action.${channel}`);
        const reason = allowed
          ? label
          : t(`channel.blocked.${state}`, {channel: t(`channel.${channel}`)});
        const button = (
          <Button
            type="button"
            size="icon"
            variant={suggested === channel && allowed ? 'secondary' : 'ghost'}
            aria-label={allowed ? label : `${label}: ${reason}`}
            disabled={!allowed}
            onClick={() => {
              if (channel === 'call' && phone)
                window.location.href = `tel:${phone}`;
              onSelect(channel);
            }}
          >
            <ChannelIcon channel={channel} />
          </Button>
        );
        return (
          <Tooltip key={channel}>
            <TooltipTrigger asChild>
              {/* A disabled button gets no pointer events; the span keeps the tooltip reachable. */}
              <span tabIndex={allowed ? -1 : 0}>{button}</span>
            </TooltipTrigger>
            <TooltipContent>{reason}</TooltipContent>
          </Tooltip>
        );
      })}
    </div>
  );
}
