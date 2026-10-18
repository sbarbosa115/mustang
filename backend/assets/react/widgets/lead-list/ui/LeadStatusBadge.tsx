import {useTranslation} from 'react-i18next';
import type {Schema} from '@/shared/api';
import {Badge} from '@/shared/ui/badge';

const VARIANT = {
  new: 'default',
  assigned: 'secondary',
  contacted: 'secondary',
  closed: 'outline',
} as const;

/** A lead's status as a word in a badge (never colour alone). */
export function LeadStatusBadge({status}: {status: Schema<'LeadStatus'>}) {
  const {t} = useTranslation('leads');
  return <Badge variant={VARIANT[status]}>{t(`status.${status}`)}</Badge>;
}
