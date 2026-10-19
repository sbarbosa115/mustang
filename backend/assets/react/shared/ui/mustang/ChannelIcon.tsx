import {Mail, MessageSquare, Phone, Video} from 'lucide-react';
import {cn} from '../../lib/utils';

const ICONS = {call: Phone, text: MessageSquare, email: Mail, video: Video};
const COLORS = {
  call: 'text-channel-call',
  text: 'text-channel-text',
  email: 'text-channel-email',
  video: 'text-channel-video',
};

/** The Lucide icon of a channel in its channel colour (DESIGN.md "Channels"). Decorative: pair it with a label. */
export function ChannelIcon({
  channel,
  className,
}: {
  channel: keyof typeof ICONS;
  className?: string;
}) {
  const Icon = ICONS[channel];
  return (
    <Icon aria-hidden className={cn('size-4', COLORS[channel], className)} />
  );
}
