import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, type RenderOptions} from '@testing-library/react';
import type {ReactElement, ReactNode} from 'react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {createI18n} from '../i18n';
import {TooltipProvider} from '../ui/tooltip';

/**
 * Renders with every provider the app has (i18n in strict mode, query client, router, tooltips), so a test sees
 * what a user sees. `route` sets the starting URL.
 */
export function renderWithProviders(
  ui: ReactElement,
  {route = '/', ...options}: RenderOptions & {route?: string} = {},
) {
  const queryClient = new QueryClient({
    defaultOptions: {queries: {retry: false}, mutations: {retry: false}},
  });
  const i18n = createI18n({strict: true});
  function Wrapper({children}: {children: ReactNode}) {
    return (
      <I18nextProvider i18n={i18n}>
        <QueryClientProvider client={queryClient}>
          <TooltipProvider>
            <MemoryRouter initialEntries={[route]}>{children}</MemoryRouter>
          </TooltipProvider>
        </QueryClientProvider>
      </I18nextProvider>
    );
  }
  return {queryClient, ...render(ui, {wrapper: Wrapper, ...options})};
}
