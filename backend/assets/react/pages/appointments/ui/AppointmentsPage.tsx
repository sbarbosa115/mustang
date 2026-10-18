import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "appointments" builds this page (docs/pdr/prd-mvp.md). */
export function AppointmentsPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.appointments')} item="appointments" />;
}
