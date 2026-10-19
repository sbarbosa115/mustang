import type {Customer} from '@/entities/customer';
import type {Channel} from '@/shared/ui/mustang';

/** The customer's contacts a channel can use, primary first: phones for text and call, emails for email. */
export function contactsFor(customer: Customer, channel: Channel): string[] {
  const kind = channel === 'email' ? 'email' : 'phone';
  return customer.contacts
    .filter((contact) => contact.kind === kind)
    .sort((a, b) => Number(b.is_primary) - Number(a.is_primary))
    .map((contact) => contact.value);
}
