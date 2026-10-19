import {formatDateTime, formatRelative} from '../../lib/dates';
import {Tooltip, TooltipContent, TooltipTrigger} from '../tooltip';

interface Props {
  iso: string;
  timeZone?: string;
  className?: string;
}

/** "5 min ago" (or a date after a day) with the full timestamp in a tooltip. */
export function RelativeTime({iso, timeZone, className}: Props) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <time dateTime={iso} className={className}>
          {formatRelative(iso, new Date(), timeZone)}
        </time>
      </TooltipTrigger>
      <TooltipContent>{formatDateTime(iso, timeZone)}</TooltipContent>
    </Tooltip>
  );
}
