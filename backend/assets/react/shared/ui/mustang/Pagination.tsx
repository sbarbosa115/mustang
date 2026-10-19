import {ChevronLeft, ChevronRight} from 'lucide-react';
import {useTranslation} from 'react-i18next';
import {Button} from '../button';

interface Props {
  page: number;
  perPage: number;
  total: number;
  onPage: (page: number) => void;
}

/** "21–40 of 132" with previous and next, under every paged list. */
export function Pagination({page, perPage, total, onPage}: Props) {
  const {t} = useTranslation('common');
  const last = Math.max(1, Math.ceil(total / perPage));
  if (total <= perPage) return null;
  const from = (page - 1) * perPage + 1;
  const to = Math.min(total, page * perPage);
  return (
    <nav
      className="flex items-center justify-between gap-2"
      aria-label={t('list.pagination')}
    >
      <p className="tabular text-sm text-muted-foreground">
        {t('list.range', {from, to, total})}
      </p>
      <div className="flex gap-1">
        <Button
          variant="outline"
          size="icon"
          aria-label={t('list.previous')}
          disabled={page <= 1}
          onClick={() => onPage(page - 1)}
        >
          <ChevronLeft />
        </Button>
        <Button
          variant="outline"
          size="icon"
          aria-label={t('list.next')}
          disabled={page >= last}
          onClick={() => onPage(page + 1)}
        >
          <ChevronRight />
        </Button>
      </div>
    </nav>
  );
}
