import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {CaptureLeadForm} from './CaptureLeadForm';

const captureLead = vi.hoisted(() => vi.fn());
const lookupCustomers = vi.hoisted(() => vi.fn());
vi.mock('@/entities/lead', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/lead')>()),
  captureLead,
}));
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  lookupCustomers,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  listUsers: () =>
    Promise.resolve({
      items: [
        {id: 'u1', name: 'Sam Patel', role: 'sales', active: true},
        {id: 'u2', name: 'Brianna Diaz', role: 'bdc', active: true},
        {id: 'u3', name: 'Ada Admin', role: 'admin', active: true},
        {id: 'u4', name: 'Gone Rep', role: 'sales', active: false},
      ],
      total: 4,
      page: 1,
      per_page: 100,
    }),
}));

function renderForm() {
  const onCaptured = vi.fn();
  const onCancel = vi.fn();
  renderWithProviders(
    <CaptureLeadForm onCaptured={onCaptured} onCancel={onCancel} />,
  );
  return {onCaptured, onCancel};
}

async function chooseSource(name: string) {
  await userEvent.click(screen.getByRole('combobox', {name: 'Source'}));
  await userEvent.click(await screen.findByRole('option', {name}));
}

const submit = () => screen.getByRole('button', {name: 'Capture lead'});

describe('CaptureLeadForm', () => {
  beforeEach(() => {
    captureLead.mockReset();
    lookupCustomers.mockReset();
    lookupCustomers.mockResolvedValue({matches: []});
  });

  it('keeps Capture lead disabled until there is a source and a phone or an email', async () => {
    renderForm();
    expect(submit()).toBeDisabled();

    await userEvent.type(screen.getByLabelText('Phone'), '8135557001');
    expect(submit(), 'a phone without a source is not enough').toBeDisabled();

    await chooseSource('Walk-in');
    await waitFor(() => expect(submit()).toBeEnabled());
  });

  it('names the customer already on file for the phone typed', async () => {
    lookupCustomers.mockResolvedValue({
      matches: [{id: 'c1', name: 'Sarah Johnson'}],
    });
    renderForm();
    await userEvent.type(screen.getByLabelText('Phone'), '(813) 555-1001');

    expect(
      await screen.findByText(
        'Adds the lead to Sarah Johnson, who is already a customer.',
      ),
    ).toBeVisible();
    expect(lookupCustomers).toHaveBeenCalledWith({phone: '(813) 555-1001'});
  });

  it('offers round robin first and only active reps who can take leads', async () => {
    renderForm();
    await userEvent.click(screen.getByRole('combobox', {name: 'Rep'}));

    const options = await screen.findAllByRole('option');
    expect(options.map((o) => o.textContent)).toEqual([
      'Next in the round robin',
      'Sam Patel',
      'Brianna Diaz',
    ]);
  });

  it('sends the trimmed values, round robin as no assignee, and reports the lead', async () => {
    const lead = {id: 'l1', customer: {id: 'c9', name: 'Lena Ortiz'}};
    captureLead.mockResolvedValue(lead);
    const {onCaptured} = renderForm();
    await chooseSource('Phone call');
    await userEvent.type(screen.getByLabelText('Phone'), ' 8135557001 ');
    await userEvent.type(screen.getByLabelText('First name'), 'Lena');
    await userEvent.type(screen.getByLabelText('Last name'), 'Ortiz ');
    await userEvent.type(
      screen.getByLabelText('Vehicle of interest'),
      'RAV4 Hybrid',
    );
    await waitFor(() => expect(submit()).toBeEnabled());
    await userEvent.click(submit());

    await waitFor(() => expect(onCaptured).toHaveBeenCalledWith(lead));
    expect(captureLead).toHaveBeenCalledWith({
      source: 'phone',
      customer_id: null,
      first_name: 'Lena',
      last_name: 'Ortiz',
      phone: '8135557001',
      email: null,
      vehicle_interest_text: 'RAV4 Hybrid',
      channel_note: null,
      assigned_user_id: null,
      received_at: null,
    });
  });

  it('asks for a name when the API says this is a new customer', async () => {
    captureLead.mockRejectedValue(
      new ApiError(422, 'customer_name_required', 'name', [], {
        field: 'first_name',
      }),
    );
    const {onCaptured} = renderForm();
    await chooseSource('Walk-in');
    await userEvent.type(screen.getByLabelText('Email'), 'new@example.com');
    await waitFor(() => expect(submit()).toBeEnabled());
    await userEvent.click(submit());

    expect(
      await screen.findByText(
        'This is a new customer: enter a first and a last name.',
      ),
    ).toBeVisible();
    expect(onCaptured).not.toHaveBeenCalled();
  });

  it('shows a refusal by its code', async () => {
    captureLead.mockRejectedValue(
      new ApiError(422, 'assignee_not_found', 'gone'),
    );
    renderForm();
    await chooseSource('Walk-in');
    await userEvent.type(screen.getByLabelText('Phone'), '8135557002');
    await waitFor(() => expect(submit()).toBeEnabled());
    await userEvent.click(submit());

    expect(
      await screen.findByText(
        'This rep is no longer active in the dealership.',
      ),
    ).toBeVisible();
  });

  it('closes without saving on Cancel', async () => {
    const {onCancel} = renderForm();
    await userEvent.click(screen.getByRole('button', {name: 'Cancel'}));
    expect(onCancel).toHaveBeenCalled();
    expect(captureLead).not.toHaveBeenCalled();
  });
});
