import {lazy, Suspense, type ComponentType, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {createBrowserRouter, Navigate, Outlet, useLocation} from 'react-router';
import {hasRole, useAuth, type Role} from '@/entities/user';
import {Skeleton} from '@/shared/ui/skeleton';
import {AppShell} from './shell/AppShell';

/** Every page behind sign-in is its own chunk (steps/04-build.md "UI/UX rules"). */
function page(load: () => Promise<Record<string, unknown>>, name: string) {
  const Component = lazy(async () => ({
    default: (await load())[name] as ComponentType,
  }));
  return (
    <Suspense fallback={<PageSkeleton />}>
      <Component />
    </Suspense>
  );
}

function PageSkeleton() {
  return (
    <div className="space-y-4" aria-busy="true">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-64 w-full" />
    </div>
  );
}

function Splash() {
  const {t} = useTranslation('shell');
  return (
    <div
      className="flex min-h-dvh items-center justify-center"
      aria-busy="true"
      aria-label={t('loading')}
    >
      <Skeleton className="size-10 rounded-md" />
    </div>
  );
}

function RequireAuth() {
  const {me, isLoading} = useAuth();
  const location = useLocation();
  if (isLoading) return <Splash />;
  if (!me)
    return (
      <Navigate
        to="/login"
        replace
        state={{from: location.pathname + location.search}}
      />
    );
  return <Outlet />;
}

/** A role-only screen sends everyone else back to Today; the API refuses them anyway. */
function RequireRole({role, children}: {role: Role; children: ReactNode}) {
  const {me} = useAuth();
  return hasRole(me, role) ? children : <Navigate to="/today" replace />;
}

export const router = createBrowserRouter([
  {path: '/login', element: page(() => import('@/pages/login'), 'LoginPage')},
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppShell />,
        children: [
          {index: true, element: <Navigate to="/today" replace />},
          {
            path: 'today',
            element: page(() => import('@/pages/today'), 'TodayPage'),
          },
          {
            path: 'inbox',
            element: page(() => import('@/pages/inbox'), 'InboxPage'),
          },
          {
            path: 'inbox/:conversationId',
            element: page(
              () => import('@/pages/inbox-thread'),
              'InboxThreadPage',
            ),
          },
          {
            path: 'customers',
            element: page(() => import('@/pages/customers'), 'CustomersPage'),
          },
          {
            path: 'customers/:customerId',
            element: page(
              () => import('@/pages/customer-detail'),
              'CustomerDetailPage',
            ),
          },
          {
            path: 'customers/:customerId/consent',
            element: page(
              () => import('@/pages/customer-consent'),
              'CustomerConsentPage',
            ),
          },
          {
            path: 'leads',
            element: page(() => import('@/pages/leads'), 'LeadsPage'),
          },
          {
            path: 'leads/:leadId',
            element: page(
              () => import('@/pages/lead-detail'),
              'LeadDetailPage',
            ),
          },
          {
            path: 'deals',
            element: page(() => import('@/pages/deals'), 'DealsPage'),
          },
          {
            path: 'deals/:dealId',
            element: page(
              () => import('@/pages/deal-detail'),
              'DealDetailPage',
            ),
          },
          {
            path: 'appointments',
            element: page(
              () => import('@/pages/appointments'),
              'AppointmentsPage',
            ),
          },
          {
            path: 'inventory',
            element: page(() => import('@/pages/inventory'), 'InventoryPage'),
          },
          {
            path: 'inventory/:vehicleId',
            element: page(
              () => import('@/pages/vehicle-detail'),
              'VehicleDetailPage',
            ),
          },
          {
            path: 'dashboard',
            element: (
              <RequireRole role="manager">
                {page(() => import('@/pages/dashboard'), 'DashboardPage')}
              </RequireRole>
            ),
          },
          {
            path: 'team',
            element: (
              <RequireRole role="manager">
                {page(() => import('@/pages/team'), 'TeamPage')}
              </RequireRole>
            ),
          },
          {
            path: 'settings',
            element: page(() => import('@/pages/settings'), 'SettingsPage'),
          },
          {
            path: 'settings/security',
            element: page(
              () => import('@/pages/settings-security'),
              'SettingsSecurityPage',
            ),
          },
          {
            path: 'settings/users',
            element: (
              <RequireRole role="admin">
                {page(
                  () => import('@/pages/settings-users'),
                  'SettingsUsersPage',
                )}
              </RequireRole>
            ),
          },
          {
            path: 'settings/templates',
            element: (
              <RequireRole role="manager">
                {page(
                  () => import('@/pages/settings-templates'),
                  'SettingsTemplatesPage',
                )}
              </RequireRole>
            ),
          },
          {
            path: 'settings/import',
            element: (
              <RequireRole role="manager">
                {page(
                  () => import('@/pages/settings-import'),
                  'SettingsImportPage',
                )}
              </RequireRole>
            ),
          },
          {
            path: 'settings/routing',
            element: (
              <RequireRole role="manager">
                {page(
                  () => import('@/pages/settings-routing'),
                  'SettingsRoutingPage',
                )}
              </RequireRole>
            ),
          },
          {
            path: 'settings/suppressions',
            element: page(
              () => import('@/pages/settings-suppressions'),
              'SettingsSuppressionsPage',
            ),
          },
          {
            path: 'settings/audit',
            element: (
              <RequireRole role="admin">
                {page(
                  () => import('@/pages/settings-audit'),
                  'SettingsAuditPage',
                )}
              </RequireRole>
            ),
          },
          {
            path: '*',
            element: page(() => import('@/pages/not-found'), 'NotFoundPage'),
          },
        ],
      },
    ],
  },
]);
