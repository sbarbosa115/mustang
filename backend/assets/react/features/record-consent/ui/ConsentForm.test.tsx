import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Customer} from '@/entities/customer';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {ConsentForm} from './ConsentForm';

const recordConsent = vi.hoisted(() => vi.fn());
vi.mock('@/entities/consent', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/consent')>()),
  recordConsent,
}));

const customer = {
  id: 'c1',
  name: 'Jessica Brown',
  preferred_channel: null,
  contacts: [
    {
      id: 'p2',
      kind: 'phone',
      value: '+18135550199',
      is_primary: false,
      label: null,
    },
    {
      id: 'p1',
      kind: 'phone',
      value: '+18135551003',
      is_primary: true,
      label: 'mobile',
    },
    {
      id: 'e1',
      kind: 'email',
      value: 'jessica.brown@example.com',
      is_primary: true,
      label: null,
    },
  ],
} as unknown as Customer;

const saved = {
  id: 'k1',
  customer_id: 'c1',
  contact_value: '+18135551003',
  channel: 'text',
  state: 'granted',
  source: 'verbal',
  captured_at: '2026-10-18T12:00:00+00:00',
  captured_by: {id: 'u1', name: 'Sam Patel'},
  captured_where: null,
  wording: null,
  campaign: null,
  revoked_at: null,
  revoked_reason: null,
};

describe('ConsentForm', () => {
  beforeEach(() => {
    recordConsent.mockReset();
  });

  it('records text consent for the primary phone by default, with where, wording and campaign', async () => {
    const user = userEvent.setup();
    const onDone = vi.fn();
    recordConsent.mockResolvedValue(saved);
    renderWithProviders(<ConsentForm customer={customer} onDone={onDone} />);

    expect(
      screen.getByRole('combobox', {name: 'Phone number or email'}),
    ).toHaveTextContent('(813) 555-1003');
    await user.type(screen.getByLabelText('Where'), ' Showroom desk ');
    await user.type(
      screen.getByLabelText('What they agreed to'),
      'Texts about my car.',
    );
    await user.type(screen.getByLabelText('Campaign'), 'Fall event');
    await user.click(screen.getByRole('button', {name: 'Record consent'}));

    await waitFor(() =>
      expect(recordConsent).toHaveBeenCalledWith('c1', {
        channel: 'text',
        contact_value: '+18135551003',
        source: 'verbal',
        captured_where: 'Showroom desk',
        captured_at: null,
        wording: 'Texts about my car.',
        campaign: 'Fall event',
      }),
    );
    await waitFor(() => expect(onDone).toHaveBeenCalled());
  });

  it('offers the emails when the channel is email', async () => {
    const user = userEvent.setup();
    recordConsent.mockResolvedValue({...saved, channel: 'email'});
    renderWithProviders(<ConsentForm customer={customer} onDone={vi.fn()} />);

    await user.click(screen.getByRole('combobox', {name: 'Channel'}));
    await user.click(await screen.findByRole('option', {name: 'Email'}));

    expect(
      screen.getByRole('combobox', {name: 'Phone number or email'}),
    ).toHaveTextContent('jessica.brown@example.com');
    await user.click(screen.getByRole('button', {name: 'Record consent'}));
    await waitFor(() =>
      expect(recordConsent).toHaveBeenCalledWith(
        'c1',
        expect.objectContaining({
          channel: 'email',
          contact_value: 'jessica.brown@example.com',
        }),
      ),
    );
  });

  it('says why it cannot record when the customer has no contact for the channel', async () => {
    const user = userEvent.setup();
    const phoneOnly = {
      ...customer,
      contacts: customer.contacts.filter((c) => c.kind === 'phone'),
    } as Customer;
    renderWithProviders(<ConsentForm customer={phoneOnly} onDone={vi.fn()} />);

    await user.click(screen.getByRole('combobox', {name: 'Channel'}));
    await user.click(await screen.findByRole('option', {name: 'Email'}));

    expect(
      screen.getByText(
        'This customer has no email. Add one before recording email consent.',
      ),
    ).toBeVisible();
    await waitFor(() =>
      expect(
        screen.getByRole('button', {name: 'Record consent'}),
      ).toBeDisabled(),
    );
  });

  it('turns the date the API refuses into a message on the field', async () => {
    const user = userEvent.setup();
    recordConsent.mockImplementation(async () => {
      throw new ApiError(422, 'captured_at_in_future', 'In the future.');
    });
    renderWithProviders(<ConsentForm customer={customer} onDone={vi.fn()} />);

    await user.type(screen.getByLabelText('When'), '2099-01-01T10:00');
    await user.click(screen.getByRole('button', {name: 'Record consent'}));

    expect(
      await screen.findByText('Consent cannot be captured in the future.'),
    ).toBeVisible();
    expect(recordConsent.mock.calls[0]?.[1].captured_at).toBe(
      '2099-01-01T15:00:00.000Z',
    );
  });
});
