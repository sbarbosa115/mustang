import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {CustomerForm} from './CustomerForm';

const createCustomer = vi.hoisted(() => vi.fn());
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  createCustomer,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({me: {user: {id: 'u1', role: 'sales'}}}),
  listUsers: () =>
    Promise.resolve({
      items: [{id: 'u1', name: 'Jordan Reyes', role: 'sales', active: true}],
      total: 1,
      page: 1,
      per_page: 100,
    }),
}));

function renderForm() {
  const onCreated = vi.fn();
  const onCancel = vi.fn();
  renderWithProviders(
    <CustomerForm onCreated={onCreated} onCancel={onCancel} />,
  );
  return {onCreated, onCancel};
}

async function fill(first: string, last: string) {
  await userEvent.type(screen.getByLabelText('First name'), first);
  await userEvent.type(screen.getByLabelText('Last name'), last);
}

describe('CustomerForm', () => {
  beforeEach(() => {
    createCustomer.mockReset();
  });

  it('keeps Add customer disabled until there is a name and a phone or an email', async () => {
    renderForm();
    const submit = screen.getByRole('button', {name: 'Add customer'});
    expect(submit).toBeDisabled();

    await fill('Mia', 'Okafor');
    expect(submit, 'a name alone is not enough').toBeDisabled();

    await userEvent.type(screen.getByLabelText('Phone'), '8135557777');
    await waitFor(() => expect(submit).toBeEnabled());
  });

  it('accepts an email in place of a phone and rejects a malformed one', async () => {
    renderForm();
    await fill('Mia', 'Okafor');
    await userEvent.type(screen.getByLabelText('Email'), 'nope');
    expect(
      await screen.findByText('Enter a valid email address.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Add customer'})).toBeDisabled();

    await userEvent.type(screen.getByLabelText('Email'), '@example.com');
    await waitFor(() =>
      expect(screen.getByRole('button', {name: 'Add customer'})).toBeEnabled(),
    );
  });

  it('sends the trimmed values with the signed-in rep as Sales 1 and reports the new customer', async () => {
    createCustomer.mockResolvedValue({id: 'c1', name: 'Mia Okafor'});
    const {onCreated} = renderForm();
    await fill(' Mia ', 'Okafor');
    await userEvent.type(screen.getByLabelText('Phone'), ' 8135557777 ');
    await userEvent.click(screen.getByRole('button', {name: 'Add customer'}));

    await waitFor(() =>
      expect(onCreated).toHaveBeenCalledWith({id: 'c1', name: 'Mia Okafor'}),
    );
    expect(createCustomer).toHaveBeenCalledWith({
      first_name: 'Mia',
      last_name: 'Okafor',
      phone: '8135557777',
      email: null,
      preferred_channel: null,
      sales1_user_id: 'u1',
    });
  });

  it('offers the existing customer when the phone is already on file', async () => {
    createCustomer.mockImplementation(() =>
      Promise.reject(
        new ApiError(409, 'duplicate_contact', 'dup', [], {
          customer_id: 'c9',
          kind: 'phone',
        }),
      ),
    );
    const {onCreated} = renderForm();
    await fill('Mia', 'Okafor');
    await userEvent.type(screen.getByLabelText('Phone'), '8135551001');
    await userEvent.click(screen.getByRole('button', {name: 'Add customer'}));

    const link = await screen.findByRole('link', {name: 'Open that customer'});
    expect(link).toHaveAttribute('href', '/customers/c9');
    expect(screen.getByText(/phone number already exists/)).toBeVisible();
    expect(onCreated).not.toHaveBeenCalled();
  });

  it('shows the API message next to the field it names', async () => {
    createCustomer.mockImplementation(() =>
      Promise.reject(
        new ApiError(422, 'validation', 'bad', [
          {
            field: 'last_name',
            code: 'too_long',
            message: 'This value is too long.',
          },
        ]),
      ),
    );
    renderForm();
    await fill('Mia', 'Okafor');
    await userEvent.type(screen.getByLabelText('Email'), 'mia@example.com');
    await userEvent.click(screen.getByRole('button', {name: 'Add customer'}));

    expect(await screen.findByText('This value is too long.')).toBeVisible();
  });

  it('closes without saving on Cancel', async () => {
    const {onCancel} = renderForm();
    await userEvent.click(screen.getByRole('button', {name: 'Cancel'}));
    expect(onCancel).toHaveBeenCalled();
    expect(createCustomer).not.toHaveBeenCalled();
  });
});
