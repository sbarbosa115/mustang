import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  type ReactNode,
} from 'react';
import {fetchMe, usersKeys} from '../api';
import {
  api,
  ApiError,
  UNAUTHENTICATED_EVENT,
  unwrap,
  type Schema,
} from '@/shared/api';
import {useTheme} from '@/shared/lib/theme';
import type {Me} from './types';

interface AuthContextValue {
  me: Me | null;
  isLoading: boolean;
  signIn: (email: string, password: string) => Promise<Schema<'LoginOutput'>>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);
const ME_KEY = [...usersKeys.all, 'me'] as const;

async function fetchMeOrNull(): Promise<Me | null> {
  try {
    return await fetchMe();
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null;
    throw error;
  }
}

/** Who is signed in. A 401 from any API call signs the app out (the session expired or was ended). */
export function AuthProvider({children}: {children: ReactNode}) {
  const queryClient = useQueryClient();
  const {setPreference} = useTheme();
  const {data, isLoading} = useQuery({
    queryKey: ME_KEY,
    queryFn: fetchMeOrNull,
    staleTime: 60_000,
  });

  useEffect(() => {
    if (data) setPreference(data.user.theme);
  }, [data, setPreference]);

  useEffect(() => {
    const onUnauthenticated = () => queryClient.setQueryData(ME_KEY, null);
    window.addEventListener(UNAUTHENTICATED_EVENT, onUnauthenticated);
    return () =>
      window.removeEventListener(UNAUTHENTICATED_EVENT, onUnauthenticated);
  }, [queryClient]);

  const signIn = useCallback(
    async (email: string, password: string) => {
      const result = unwrap(
        await api.POST('/api/v1/auth/login', {body: {email, password}}),
      );
      if (result.next === 'ok')
        await queryClient.invalidateQueries({queryKey: ME_KEY});
      return result;
    },
    [queryClient],
  );

  const signOut = useCallback(async () => {
    await api.POST('/api/v1/auth/logout');
    // Set the session to "nobody" on the query the app observes, then drop every other cached record so the next
    // user never sees the previous one's data. (clear() would detach the observed session query.)
    queryClient.setQueryData(ME_KEY, null);
    queryClient.removeQueries({
      predicate: (query) => query.queryKey[1] !== 'me',
    });
  }, [queryClient]);

  const value = useMemo(
    () => ({me: data ?? null, isLoading, signIn, signOut}),
    [data, isLoading, signIn, signOut],
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth needs an AuthProvider');
  return context;
}
