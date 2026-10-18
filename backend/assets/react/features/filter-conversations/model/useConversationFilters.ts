import {useSearchParams} from 'react-router';

export type Folder = 'all' | 'unreplied' | 'fumbled';
export const FOLDERS: Folder[] = ['all', 'unreplied', 'fumbled'];

export interface ConversationFilters {
  folder: Folder;
  userId: string | null;
  q: string;
  page: number;
  /** True when a search or a rep narrows the list (a folder is a view, not a filter). */
  filtered: boolean;
  setFolder: (folder: Folder) => void;
  setUserId: (userId: string | null) => void;
  setQ: (q: string) => void;
  setPage: (page: number) => void;
  /** Every filter off, back to All: what "Show all" does. */
  clear: () => void;
}

/** The inbox filters live in the URL (?folder, ?user_id, ?q, ?page), so a link or a reload keeps them. */
export function useConversationFilters(): ConversationFilters {
  const [params, setParams] = useSearchParams();
  const folderParam = params.get('folder') ?? '';
  const folder = (FOLDERS as string[]).includes(folderParam)
    ? (folderParam as Folder)
    : 'all';
  const userId = params.get('user_id') || null;
  const q = params.get('q') ?? '';
  const page = Math.max(1, Number(params.get('page')) || 1);

  const update = (changes: Record<string, string | null>) =>
    setParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        for (const [key, value] of Object.entries(changes)) {
          if (value == null || value === '') next.delete(key);
          else next.set(key, value);
        }
        if (!('page' in changes)) next.delete('page');
        return next;
      },
      {replace: true},
    );

  return {
    folder,
    userId,
    q,
    page,
    filtered: userId !== null || q !== '',
    setFolder: (value) => update({folder: value === 'all' ? null : value}),
    setUserId: (value) => update({user_id: value}),
    setQ: (value) => update({q: value.trim()}),
    setPage: (value) => update({page: value > 1 ? String(value) : null}),
    clear: () => update({folder: null, user_id: null, q: null}),
  };
}
