import {MapPinOff} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {Button} from '@/shared/ui/button';
import {EmptyState} from '@/shared/ui/mustang';

export function NotFoundPage() {
  const {t} = useTranslation('shell');
  return (
    <EmptyState
      icon={MapPinOff}
      title={t('not_found.title')}
      action={
        <Button asChild>
          <Link to="/today">{t('not_found.action')}</Link>
        </Button>
      }
    />
  );
}
