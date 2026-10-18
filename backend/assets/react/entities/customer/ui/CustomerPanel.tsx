import {Mail, Phone} from 'lucide-react';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {formatPhone} from '@/shared/lib/phone';
import {Card, CardContent, CardHeader, CardTitle} from '@/shared/ui/card';
import {
  ConsentIndicator,
  StageBadge,
  type DealStage,
} from '@/shared/ui/mustang';

interface Props {
  customer: Schema<'CustomerOutput'>;
  /** Actions for the panel's header (edit, assign); features slot them in. */
  actions?: ReactNode;
}

/** Contact details, assigned team, open deal and consent (DESIGN.md "CustomerPanel"; CRM-01). */
export function CustomerPanel({customer, actions}: Props) {
  const {t} = useTranslation('customers');
  const team = [
    ['sales1', customer.assignments.sales1],
    ['sales2', customer.assignments.sales2],
    ['bdc', customer.assignments.bdc],
  ] as const;
  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-2">
        <CardTitle className="text-xl">{customer.name}</CardTitle>
        {actions}
      </CardHeader>
      <CardContent className="space-y-4">
        <ul className="space-y-1 text-sm">
          {customer.contacts.map((contact) => (
            <li key={contact.id} className="flex items-center gap-2">
              {contact.kind === 'phone' ? (
                <Phone
                  aria-label={t('panel.phone')}
                  className="size-4 text-muted-foreground"
                />
              ) : (
                <Mail
                  aria-label={t('panel.email')}
                  className="size-4 text-muted-foreground"
                />
              )}
              <span>
                {contact.kind === 'phone'
                  ? formatPhone(contact.value)
                  : contact.value}
              </span>
              {contact.label && (
                <span className="text-xs text-muted-foreground">
                  {contact.label}
                </span>
              )}
            </li>
          ))}
        </ul>
        <ConsentIndicator availability={customer.consent} />
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
          {team.map(([key, user]) => (
            <div key={key} className="contents">
              <dt className="text-muted-foreground">
                {t(`panel.team.${key}`)}
              </dt>
              <dd>{user?.name ?? t('panel.team.none')}</dd>
            </div>
          ))}
        </dl>
        {customer.open_deal && (
          <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">
              {t('panel.open_deal')}
            </span>
            <StageBadge
              stage={customer.open_deal.stage as DealStage}
              daysInStage={customer.open_deal.days_in_stage}
            />
            {customer.open_deal.vehicle_text && (
              <span>{customer.open_deal.vehicle_text}</span>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
