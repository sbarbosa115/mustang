import {useTranslation} from 'react-i18next';
import {PagePlaceholder} from '@/shared/ui/page';

/** Placeholder until item "inbox-ui" builds this page (docs/pdr/prd-mvp.md). */
export function InboxPage() {
  const {t} = useTranslation('shell');
  return <PagePlaceholder title={t('nav.inbox')} item="inbox-ui" />;
}
