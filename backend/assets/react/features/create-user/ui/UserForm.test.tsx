import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {UserForm} from './UserForm';

const createUser = vi.hoisted(() => vi.fn());
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  createUser,
}));

function renderForm() {
  const onCreated = vi.fn();
  const onCancel = vi.fn();
  renderWithProviders(<UserForm onCreated={onCreated} onCancel={onCancel} />);
  return {onCreated, onCancel};
}

async function fill(email = 'nina@mustang.test', password = 'a-long-password') {
  await userEvent.type(screen.getByLabelText('First name'), 'Nina');
  await userEvent.type(screen.getByLabelText('Last name'), 'Newhire');
  await userEvent.type(screen.getByLabelText('Email'), email);
  await userEvent.type(screen.getByLabelText('Temporary password'), password);
}

describe('UserForm', () => {
  beforeEach(() => {
    createUser.mockReset();
  });

  it('keeps Add user disabled until every field is valid', async () => {
    renderForm();
    const submit = screen.getByRole('button', {name: 'Add user'});
    expect(submit).toBeDisabled();

    await fill();
    await waitFor(() => expect(submit).toBeEnabled());
  });

  it('asks for at least 12 characters in the password', async () => {
    renderForm();
    await fill('nina@mustang.test', 'too-short');

    expect(
      await screen.findByText('Use at least 12 characters.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Add user'})).toBeDisabled();
  });

  it('rejects a malformed email', async () => {
    renderForm();
    await fill('nope');

    expect(
      await screen.findByText('Enter a valid email address.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Add user'})).toBeDisabled();
  });

  it('sends the trimmed values with the Sales role by default and reports the user', async () => {
    createUser.mockResolvedValue({id: 'u9', name: 'Nina Newhire'});
    const {onCreated} = renderForm();
    await fill(' nina@mustang.test ');
    await userEvent.click(screen.getByRole('button', {name: 'Add user'}));

    await waitFor(() =>
      expect(onCreated).toHaveBeenCalledWith({id: 'u9', name: 'Nina Newhire'}),
    );
    expect(createUser).toHaveBeenCalledWith({
      first_name: 'Nina',
      last_name: 'Newhire',
      email: 'nina@mustang.test',
      role: 'sales',
      password: 'a-long-password',
    });
  });

  it('lets the admin pick another role', async () => {
    createUser.mockResolvedValue({id: 'u9', name: 'Nina Newhire'});
    renderForm();
    await fill();
    await userEvent.click(screen.getByLabelText('Role'));
    await userEvent.click(screen.getByRole('option', {name: 'Manager'}));
    await userEvent.click(screen.getByRole('button', {name: 'Add user'}));

    await waitFor(() =>
      expect(createUser).toHaveBeenCalledWith(
        expect.objectContaining({role: 'manager'}),
      ),
    );
  });

  it('says so next to the email when another account has it', async () => {
    createUser.mockImplementation(() =>
      Promise.reject(new ApiError(409, 'email_taken', 'taken')),
    );
    const {onCreated} = renderForm();
    await fill();
    await userEvent.click(screen.getByRole('button', {name: 'Add user'}));

    expect(
      await screen.findByText('Another account already uses this email.'),
    ).toBeVisible();
    expect(onCreated).not.toHaveBeenCalled();
  });

  it('closes without saving on Cancel', async () => {
    const {onCancel} = renderForm();
    await userEvent.click(screen.getByRole('button', {name: 'Cancel'}));
    expect(onCancel).toHaveBeenCalled();
    expect(createUser).not.toHaveBeenCalled();
  });
});
