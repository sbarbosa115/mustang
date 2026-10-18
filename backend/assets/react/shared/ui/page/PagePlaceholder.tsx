import {Construction} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Card} from '../card';
import {EmptyState} from '../mustang/EmptyState';
import {PageHeader} from './PageHeader';

/** What a route shows until the item that builds it lands (docs/pdr/prd-mvp.md, "Frontend contract"). */
export function PagePlaceholder({title, item}: {title: string; item: string}) {
  const {t} = useTranslation('shell');
  return (
    <>
      <PageHeader title={title} />
      <Card>
        <EmptyState
          icon={Construction}
          title={t('placeholder.title', {page: title})}
          description={t('placeholder.description', {item})}
        />
      </Card>
    </>
  );
}
