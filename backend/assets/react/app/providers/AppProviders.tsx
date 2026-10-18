import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {useState, type ReactNode} from 'react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/shared/i18n';
import {ThemeProvider} from '@/shared/lib/theme';
import {Toaster} from '@/shared/ui/sonner';
import {TooltipProvider} from '@/shared/ui/tooltip';
import {AuthProvider} from '@/entities/user';

const i18n = createI18n();

export function AppProviders({children}: {children: ReactNode}) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {retry: 1, refetchOnWindowFocus: true, staleTime: 15_000},
        },
      }),
  );
  return (
    <I18nextProvider i18n={i18n}>
      <ThemeProvider>
        <QueryClientProvider client={queryClient}>
          <TooltipProvider delayDuration={300}>
            <AuthProvider>{children}</AuthProvider>
            <Toaster position="top-center" />
          </TooltipProvider>
        </QueryClientProvider>
      </ThemeProvider>
    </I18nextProvider>
  );
}
