import {screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import type {ChannelAvailability} from '@/shared/ui/mustang';
import {renderWithProviders} from '@/shared/test/render';
import {Composer, type ComposerTools} from './Composer';

const sendMessage = vi.hoisted(() => vi.fn());
vi.mock('@/entities/message', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/message')>()),
  sendMessage,
}));

const both: ChannelAvailability = {
  text: 'granted',
  email: 'granted',
  call: 'missing',
};
const contacts = {phone: '+18135551001', email: 'ana@example.com'};
const sent = {
  id: 'm1',
  conversation_id: 'v1',
  direction: 'out',
  channel: 'text',
  body: 'Hello',
  status: 'sent',
  to_value: '+18135551001',
  from_value: '+18135550100',
  created_at: '2026-10-18T12:00:00+00:00',
};

function renderComposer(
  props: Partial<Parameters<typeof Composer>[0]> = {},
) {
  return renderWithProviders(
    <Composer
      conversationId="v1"
      customerId="c1"
      consent={both}
      contacts={contacts}
      values={{
        first_name: 'Ana',
        last_name: 'Lopez',
        rep_name: 'Sam Patel',
        dealership: 'Mustang Motors',
      }}
      {...props}
    />,
  );
}

describe('Composer', () => {
  beforeEach(() => {
    sendMessage.mockReset();
  });

  it('shows consent per channel before anything is sent, and sends a text', async () => {
    const user = userEvent.setup();
    sendMessage.mockResolvedValue(sent);
    renderComposer();

    expect(screen.getByText('Text: Granted')).toBeVisible();
    expect(screen.getByText('Email: Granted')).toBeVisible();
    expect(screen.getByRole('button', {name: 'Text'})).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    const send = screen.getByRole('button', {name: 'Send'});
    expect(send, 'nothing to send yet').toBeDisabled();

    await user.type(screen.getByLabelText('Message'), 'Hello');
    await user.click(send);

    expect(sendMessage).toHaveBeenCalledWith('v1', {
      channel: 'text',
      body: 'Hello',
      subject: null,
      template_id: null,
    });
    await waitFor(() =>
      expect(screen.getByLabelText('Message'), 'cleared once sent').toHaveValue(
        '',
      ),
    );
  });

  it('disables send with the reason and a link to record consent when consent is missing', () => {
    renderComposer({
      consent: {text: 'missing', email: 'missing', call: 'missing'},
    });

    expect(screen.getByText('No consent recorded for Text')).toBeVisible();
    expect(screen.getByText('No consent recorded for Email')).toBeVisible();
    expect(screen.getByRole('button', {name: 'Send'})).toBeDisabled();
    expect(screen.getByLabelText('Message')).toBeDisabled();
    expect(
      screen.getByRole('link', {name: 'Record consent'}),
    ).toHaveAttribute('href', '/customers/c1/consent');
  });

  it('disables a revoked channel with its reason and starts on the one that is allowed', () => {
    renderComposer({
      consent: {text: 'revoked', email: 'granted', call: 'missing'},
    });

    expect(screen.getByRole('button', {name: 'Text'})).toBeDisabled();
    expect(screen.getByText('Consent for Text was revoked')).toBeVisible();
    expect(screen.getByRole('button', {name: 'Email'})).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(screen.getByLabelText('Subject')).toBeVisible();
  });

  it('says an opted-out channel is blocked', () => {
    renderComposer({
      consent: {text: 'opted_out', email: 'missing', call: 'missing'},
    });
    expect(
      screen.getByText('This contact opted out of Text'),
    ).toBeVisible();
  });

  it('says when there is no phone number to text', () => {
    renderComposer({contacts: {phone: null, email: 'ana@example.com'}});
    expect(screen.getByRole('button', {name: 'Text'})).toBeDisabled();
    expect(screen.getByText('No phone number on file for texts')).toBeVisible();
    expect(screen.getByRole('link', {name: 'Add a contact'})).toHaveAttribute(
      'href',
      '/customers/c1',
    );
  });

  it('switches to email, which needs a subject', async () => {
    const user = userEvent.setup();
    sendMessage.mockResolvedValue({...sent, channel: 'email', status: 'queued'});
    renderComposer();

    await user.click(screen.getByRole('button', {name: 'Email'}));
    await user.type(screen.getByLabelText('Message'), 'Your quote');
    expect(screen.getByRole('button', {name: 'Send'})).toBeDisabled();
    expect(screen.getByText('An email needs a subject.')).toBeVisible();

    await user.type(screen.getByLabelText('Subject'), 'Quote');
    await user.click(screen.getByRole('button', {name: 'Send'}));
    expect(sendMessage).toHaveBeenCalledWith('v1', {
      channel: 'email',
      body: 'Your quote',
      subject: 'Quote',
      template_id: null,
    });
  });

  it('fills a template placeholders with the customer and rep, and sends its id', async () => {
    const user = userEvent.setup();
    sendMessage.mockResolvedValue(sent);
    const tools = ({insert}: ComposerTools) => (
      <button
        type="button"
        onClick={() =>
          insert({
            body: 'Hi {first_name}, this is {rep_name} at {dealership}. See you at {appointment_time}.',
            templateId: 't1',
          })
        }
      >
        Use template
      </button>
    );
    renderComposer({tools});

    await user.click(screen.getByRole('button', {name: 'Use template'}));
    expect(screen.getByLabelText('Message')).toHaveValue(
      'Hi Ana, this is Sam Patel at Mustang Motors. See you at {appointment_time}.',
    );
    expect(
      screen.getByText('Fill in {appointment_time} before sending.'),
    ).toBeVisible();

    await user.click(screen.getByRole('button', {name: 'Send'}));
    expect(sendMessage).toHaveBeenCalledWith(
      'v1',
      expect.objectContaining({template_id: 't1'}),
    );
  });

  it('shows a refusal by its code next to the composer and keeps the draft', async () => {
    const user = userEvent.setup();
    sendMessage.mockRejectedValue(
      new ApiError(409, 'opted_out', 'Opted out', [], {
        message_id: 'm9',
        consent: {state: 'opted_out'},
      }),
    );
    renderComposer();

    await user.type(screen.getByLabelText('Message'), 'Hello');
    await user.click(screen.getByRole('button', {name: 'Send'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Not sent: the customer opted out of this channel. The attempt is kept in the thread.',
    );
    expect(screen.getByLabelText('Message')).toHaveValue('Hello');
  });

  it('says when the customer has no contact for the channel', async () => {
    const user = userEvent.setup();
    sendMessage.mockRejectedValue(
      new ApiError(409, 'contact_missing', 'No contact'),
    );
    renderComposer();

    await user.type(screen.getByLabelText('Message'), 'Hello');
    await user.click(screen.getByRole('button', {name: 'Send'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Not sent: the customer has no phone number or email for this channel.',
    );
  });

  it('shows a field error from validation under the message', async () => {
    const user = userEvent.setup();
    sendMessage.mockRejectedValue(
      new ApiError(422, 'validation', 'Invalid', [
        {field: 'body', code: 'too_long', message: 'This value is too long.'},
      ]),
    );
    renderComposer();

    await user.type(screen.getByLabelText('Message'), 'Hello');
    await user.click(screen.getByRole('button', {name: 'Send'}));

    expect(await screen.findByText('This value is too long.')).toBeVisible();
  });
});
