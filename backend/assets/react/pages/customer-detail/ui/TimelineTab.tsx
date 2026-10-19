import {CustomerTimeline} from '@/widgets/customer-timeline';

/** The customer's timeline: every event that touched them, grouped by day. */
export function TimelineTab({customerId}: {customerId: string}) {
  return <CustomerTimeline customerId={customerId} />;
}
