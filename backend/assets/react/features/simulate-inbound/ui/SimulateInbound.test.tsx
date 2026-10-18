import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {SimulateInbound} from './SimulateInbound';

const simulateInbound = vi.hoisted(() => vi.fn());
vi.mock('@/entities/conversation', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/conversation')>()),
  simulateInbound,
}));

/** The probe is an empty POST: 422 means the dev endpoint is there, 404 that it is not. */
function endpoint(exposed: boolean) {
  simulateInbound.mockImplementation((body: {from?: string}) =>
    body.from
      ? Promise.resolve({
          customer_id: 'c1',
          conversation_id: 'v1',
          message_id: 'm1',
        })
      : Promise.reject(
          exposed
            ? new ApiError(422, 'validation', 'Invalid', [
                {field: 'from', code: 'blank', message: 'Required.'},
              ])
            : new ApiError(404, 'not_found', 'No route'),
        ),
  );
}

describe('SimulateInbound', () => {
  beforeEach(() => {
    simulateInbound.mockReset();
  });

  it('is not shown when the API does not expose the dev endpoint', async () => {
    endpoint(false);
    renderWithProviders(<SimulateInbound />);
    await waitFor(() =>
      expect(simulateInbound).toHaveBeenCalledWith(
        expect.objectContaining({from: null, body: null}),
      ),
    );
    expect(
      screen.queryByRole('button', {name: 'Simulate a reply'}),
      'production never shows the simulator',
    ).toBeNull();
  });

  it('adds a text reply from the customer phone it was opened for', async () => {
    const user = userEvent.setup();
    const onDone = vi.fn();
    endpoint(true);
    renderWithProviders(
      <SimulateInbound
        contacts={{phone: '+18135551005', email: 'ashley@example.com'}}
        onDone={onDone}
      />,
    );

    await user.click(
      await screen.findByRole('button', {name: 'Simulate a reply'}),
    );
    const dialog = await screen.findByRole('dialog', {
      name: 'Simulate a customer reply',
    });
    expect(screen.getByLabelText('From')).toHaveValue('+18135551005');
    await user.type(screen.getByLabelText('Message'), 'Is the Civic there?');
    await user.click(screen.getByRole('button', {name: 'Add reply'}));

    expect(simulateInbound).toHaveBeenLastCalledWith({
      from: '+18135551005',
      channel: 'text',
      body: 'Is the Civic there?',
      subject: null,
    });
    await waitFor(() => expect(dialog).not.toBeInTheDocument());
    expect(onDone).toHaveBeenCalledWith(
      expect.objectContaining({conversation_id: 'v1'}),
    );
  });

  it('switches to email with the customer address and a subject', async () => {
    const user = userEvent.setup();
    endpoint(true);
    renderWithProviders(
      <SimulateInbound
        contacts={{phone: '+18135551005', email: 'ashley@example.com'}}
      />,
    );

    await user.click(
      await screen.findByRole('button', {name: 'Simulate a reply'}),
    );
    await user.click(screen.getByRole('button', {name: 'Email'}));
    expect(screen.getByLabelText('From')).toHaveValue('ashley@example.com');
    await user.type(screen.getByLabelText('Subject'), 'Trade-in');
    await user.type(screen.getByLabelText('Message'), 'What is mine worth?');
    await user.click(screen.getByRole('button', {name: 'Add reply'}));

    expect(simulateInbound).toHaveBeenLastCalledWith({
      from: 'ashley@example.com',
      channel: 'email',
      body: 'What is mine worth?',
      subject: 'Trade-in',
    });
  });

  it('says when no customer has that contact', async () => {
    const user = userEvent.setup();
    endpoint(true);
    renderWithProviders(<SimulateInbound />);
    await user.click(
      await screen.findByRole('button', {name: 'Simulate a reply'}),
    );
    simulateInbound.mockRejectedValue(
      new ApiError(422, 'unknown_contact', 'Unknown'),
    );

    await user.type(screen.getByLabelText('From'), 'nobody@example.com');
    await user.type(screen.getByLabelText('Message'), 'Hi');
    await user.click(screen.getByRole('button', {name: 'Add reply'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No customer of this dealership has that phone number or email.',
    );
  });
});
