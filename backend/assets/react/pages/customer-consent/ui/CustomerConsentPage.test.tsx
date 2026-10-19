import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {CustomerConsentPage} from './CustomerConsentPage';

const fetchCustomer = vi.hoisted(() => vi.fn());
const listConsents = vi.hoisted(() => vi.fn());
const revokeConsent = vi.hoisted(() => vi.fn());
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  fetchCustomer,
}));
vi.mock('@/entities/consent', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/consent')>()),
  listConsents,
  revokeConsent,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({me: {dealership: {timezone: 'America/New_York'}}}),
}));

const customer = {
  id: 'c1',
  name: 'Jessica Brown',
  preferred_channel: null,
  contacts: [
    {
      id: 'p1',
      kind: 'phone',
      value: '+18135551003',
      is_primary: true,
      label: null,
    },
  ],
  consent: {text: 'missing', email: 'missing', call: 'missing'},
};
const granted = {
  id: 'k1',
  customer_id: 'c1',
  contact_value: '+18135551003',
  channel: 'text',
  state: 'granted',
  source: 'written',
  captured_at: '2026-10-18T12:00:00+00:00',
  captured_by: {id: 'u1', name: 'Sam Patel'},
  captured_where: 'Showroom desk',
  wording: 'Texts about my car.',
  campaign: null,
  revoked_at: null,
  revoked_reason: null,
};

function renderPage() {
  return renderWithProviders(
    <Routes>
      <Route
        path="/customers/:customerId/consent"
        element={<CustomerConsentPage />}
      />
    </Routes>,
    {route: '/customers/c1/consent'},
  );
}

describe('CustomerConsentPage', () => {
  beforeEach(() => {
    fetchCustomer.mockReset();
    listConsents.mockReset();
    revokeConsent.mockReset();
    fetchCustomer.mockResolvedValue(customer);
  });

  it('says nothing is recorded yet and offers to record consent', async () => {
    listConsents.mockResolvedValue({items: [], summary: customer.consent});
    renderPage();

    expect(
      await screen.findByRole('heading', {
        level: 1,
        name: 'Consent for Jessica Brown',
      }),
    ).toBeVisible();
    expect(await screen.findByText('No consent recorded yet.')).toBeVisible();
    expect(screen.getAllByText('Text: Missing')[0]).toBeVisible();
  });

  it('lists a record with its source, who and where, and revokes it after asking', async () => {
    const user = userEvent.setup();
    listConsents.mockResolvedValue({
      items: [granted],
      summary: {...customer.consent, text: 'granted'},
    });
    revokeConsent.mockResolvedValue({...granted, state: 'revoked'});
    renderPage();

    expect((await screen.findAllByText('Written form'))[0]).toBeInTheDocument();
    expect(screen.getAllByText('by Sam Patel')[0]).toBeInTheDocument();
    expect(screen.getAllByText('Showroom desk')[0]).toBeInTheDocument();

    await user.click(
      screen.getAllByRole('button', {
        name: 'Revoke Text consent for (813) 555-1003',
      })[0]!,
    );
    const dialog = await screen.findByRole('alertdialog', {
      name: 'Revoke Text consent for (813) 555-1003?',
    });
    await user.type(
      within(dialog).getByLabelText('Reason'),
      'Asked us to stop',
    );
    await user.click(
      within(dialog).getByRole('button', {name: 'Revoke consent'}),
    );

    await waitFor(() =>
      expect(revokeConsent).toHaveBeenCalledWith('k1', {
        reason: 'Asked us to stop',
      }),
    );
  });

  it('explains an opt-out', async () => {
    listConsents.mockResolvedValue({
      items: [
        {
          ...granted,
          state: 'revoked',
          source: 'sms_keyword',
          captured_by: null,
          revoked_at: '2026-10-18T13:00:00+00:00',
          revoked_reason: 'stop_keyword',
        },
      ],
      summary: {...customer.consent, text: 'opted_out'},
    });
    renderPage();

    expect(
      await screen.findByText(/Text is blocked: this contact replied STOP/),
    ).toBeVisible();
    expect(screen.getAllByText('Replied STOP')[0]).toBeInTheDocument();
    expect(
      screen.queryAllByRole('button', {name: /^Revoke Text/}),
    ).toHaveLength(0);
  });

  it('says when the customer is not in the dealership', async () => {
    const {ApiError} = await import('@/shared/api');
    fetchCustomer.mockImplementation(async () => {
      throw new ApiError(404, 'customer_not_found', 'Not found');
    });
    listConsents.mockResolvedValue({items: [], summary: customer.consent});
    renderPage();

    expect(
      await screen.findByText(
        'This customer does not exist or is not in your dealership.',
      ),
    ).toBeVisible();
  });
});
