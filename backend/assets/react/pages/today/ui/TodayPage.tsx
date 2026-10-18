import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "worklist" builds this page (docs/pdr/prd-mvp.md). */
export function TodayPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.today')} item="worklist" />;
}
