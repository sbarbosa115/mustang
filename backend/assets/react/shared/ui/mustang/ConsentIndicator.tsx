import {Ban, Check, CircleHelp, X} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {cn} from '../../lib/utils';
import {Tooltip, TooltipContent, TooltipTrigger} from '../tooltip';
import type {Channel, ChannelAvailability, ConsentState} from './channel';
import {ChannelIcon} from './ChannelIcon';

const STATE_STYLE: Record<
  ConsentState,
  {className: string; Icon: typeof Check}
> = {
  granted: {className: 'bg-success-surface text-success', Icon: Check},
  missing: {className: 'bg-warning-surface text-warning', Icon: CircleHelp},
  revoked: {className: 'bg-danger-surface text-danger', Icon: X},
  opted_out: {className: 'bg-danger-surface text-danger', Icon: Ban},
};

interface Props {
  availability: ChannelAvailability;
  /** Which channels to show; all three by default. */
  channels?: Channel[];
  /** Extra detail for the tooltip, e.g. "Granted Oct 3 on the website". */
  details?: Partial<Record<Channel, string>>;
  className?: string;
}

/**
 * Consent per channel, shown before anything is sent (DESIGN.md principle 5). Each chip has the channel icon, the
 * state as a word and an icon, so it never relies on colour.
 */
export function ConsentIndicator({
  availability,
  channels = ['text', 'email', 'call'],
  details,
  className,
}: Props) {
  const {t} = useTranslation('common');
  return (
    <ul
      className={cn('flex flex-wrap gap-2', className)}
      aria-label={t('consent.label')}
    >
      {channels.map((channel) => {
        const state = availability[channel];
        const {className: stateClass, Icon} = STATE_STYLE[state];
        const label = t('consent.chip', {
          channel: t(`channel.${channel}`),
          state: t(`consent.state.${state}`),
        });
        return (
          <li key={channel}>
            <Tooltip>
              <TooltipTrigger asChild>
                <span
                  tabIndex={0}
                  className={cn(
                    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
                    stateClass,
                  )}
                >
                  <ChannelIcon channel={channel} className="text-current" />
                  <span>{label}</span>
                  <Icon aria-hidden className="size-3.5" />
                </span>
              </TooltipTrigger>
              <TooltipContent>
                {details?.[channel] ?? t(`consent.help.${state}`)}
              </TooltipContent>
            </Tooltip>
          </li>
        );
      })}
    </ul>
  );
}
