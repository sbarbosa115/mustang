import {useQuery} from '@tanstack/react-query';
import {Search} from 'lucide-react';
import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  conversationsKeys,
  fetchConversationCounts,
} from '@/entities/conversation';
import {listUsers, usersKeys} from '@/entities/user';
import {cn} from '@/shared/lib/utils';
import {Input} from '@/shared/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/ui/select';
import {
  FOLDERS,
  type ConversationFilters as Filters,
} from '../model/useConversationFilters';

const EVERYONE = 'everyone';
const REP_ROLES = ['sales', 'bdc', 'manager'];

interface Props {
  filters: Filters;
  className?: string;
}

/** The folders with their counts, the rep filter (with each rep's unreplied count) and the search box. */
export function ConversationFilters({filters, className}: Props) {
  const {t} = useTranslation('inbox');
  const counts = useQuery({
    queryKey: [...conversationsKeys.all, 'counts'],
    queryFn: fetchConversationCounts,
    refetchInterval: 15_000,
  });
  const users = useQuery({
    queryKey: usersKeys.list({per_page: 100}),
    queryFn: () => listUsers({per_page: 100}),
    staleTime: 5 * 60_000,
  });
  const reps = (users.data?.items ?? []).filter(
    (user) => user.active && REP_ROLES.includes(user.role),
  );
  const unrepliedOf = (id: string) =>
    counts.data?.by_user.find((row) => row.user.id === id)?.count ?? 0;

  return (
    <div className={cn('space-y-3', className)}>
      <SearchBox value={filters.q} onSearch={filters.setQ} />
      <div
        role="group"
        aria-label={t('folders.label')}
        className="flex gap-1 overflow-x-auto"
      >
        {FOLDERS.map((folder) => {
          const active = filters.folder === folder;
          const count = counts.data?.[folder];
          return (
            <button
              key={folder}
              type="button"
              aria-pressed={active}
              onClick={() => filters.setFolder(folder)}
              className={cn(
                'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium sm:min-h-9',
                active
                  ? 'bg-accent text-accent-foreground'
                  : 'text-muted-foreground hover:bg-muted',
              )}
            >
              <span>{t(`folders.${folder}`)}</span>
              {count != null && (
                <span
                  className={cn(
                    'tabular rounded-full px-1.5 text-xs',
                    folder !== 'all' && count > 0
                      ? 'bg-hot-surface text-hot'
                      : 'bg-muted text-muted-foreground',
                  )}
                >
                  <span aria-hidden>{count}</span>
                  <span className="sr-only">{t('folders.count', {count})}</span>
                </span>
              )}
            </button>
          );
        })}
      </div>
      <Select
        value={filters.userId ?? EVERYONE}
        onValueChange={(value) =>
          filters.setUserId(value === EVERYONE ? null : value)
        }
      >
        <SelectTrigger aria-label={t('filters.rep')} className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={EVERYONE}>{t('filters.everyone')}</SelectItem>
          {reps.map((rep) => (
            <SelectItem key={rep.id} value={rep.id}>
              {t('filters.rep_option', {
                name: rep.name,
                count: unrepliedOf(rep.id),
              })}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

function SearchBox({
  value,
  onSearch,
}: {
  value: string;
  onSearch: (q: string) => void;
}) {
  const {t} = useTranslation('inbox');
  const [text, setText] = useState(value);
  const [previous, setPrevious] = useState(value);
  // "Show all" clears the URL: the box follows it (React's "adjust state when a prop changes").
  if (value !== previous) {
    setPrevious(value);
    if (value !== text.trim()) setText(value);
  }

  const onSearchRef = useRef(onSearch);
  useEffect(() => {
    onSearchRef.current = onSearch;
  });

  // The URL (and so the query) follows what was typed once typing pauses.
  useEffect(() => {
    const term = text.trim();
    if (term === value) return;
    const timer = setTimeout(() => onSearchRef.current(term), 300);
    return () => clearTimeout(timer);
  }, [text, value]);

  return (
    <div className="relative">
      <Search
        aria-hidden
        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
      />
      <Input
        type="search"
        aria-label={t('filters.search')}
        placeholder={t('filters.search_placeholder')}
        value={text}
        onChange={(event) => setText(event.target.value)}
        className="pl-9"
      />
    </div>
  );
}
