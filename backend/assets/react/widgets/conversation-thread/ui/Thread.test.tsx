import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {ConversationThread} from './ConversationThread';

const fetchConversation = vi.hoisted(() => vi.fn());
const markConversationRead = vi.hoisted(() => vi.fn());
const simulateInbound = vi.hoisted(() => vi.fn());
const fetchCustomer = vi.hoisted(() => vi.fn());
const listTemplates = vi.hoisted(() => vi.fn());
vi.mock('@/entities/conversation', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/conversation')>()),
  fetchConversation,
  markConversationRead,
  simulateInbound,
}));
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  fetchCustomer,
}));
vi.mock('@/entities/template', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/template')>()),
  listTemplates,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({
    me: {
      user: {id: 'u1', name: 'Sam Patel', role: 'sales'},
      dealership: {name: 'Mustang Motors', timezone: 'America/New_York'},
    },
  }),
}));

// Noon in New York on Oct 18, 2026.
const NOW = new Date('2026-10-18T16:00:00Z');

const message = (id: string, extra: Record<string, unknown>) => ({
  id,
  conversation_id: 'v1',
  direction: 'in',
  channel: 'text',
  body: '',
  status: 'received',
  to_value: '+18135550100',
  from_value: '+18135551001',
  created_at: '2026-10-18T15:00:00Z',
  sent_by: null,
  subject: null,
  failed_reason: null,
  sent_at: null,
  received_at: null,
  ...extra,
});

const detail = (extra: Record<string, unknown> = {}) => ({
  conversation: {
    id: 'v1',
    customer: {id: 'c1', name: 'Ana Lopez'},
    unread_count: 0,
    unreplied: true,
    fumbled: false,
    consent: {text: 'granted', email: 'granted', call: 'granted'},
    assignee: {id: 'u1', name: 'Sam Patel'},
    last_message: null,
    last_message_at: '2026-10-18T15:00:00Z',
  },
  contacts: {phone: '+18135551001', email: 'ana@example.com'},
  messages: [
    message('m1', {
      body: 'Do you still have the Civic?',
      created_at: '2026-10-09T14:00:00Z',
      received_at: '2026-10-09T14:00:00Z',
    }),
    message('m2', {
      direction: 'out',
      channel: 'email',
      subject: 'Your Civic',
      body: 'Yes, it is here.',
      status: 'sent',
      sent_by: {id: 'u1', name: 'Sam Patel'},
      created_at: '2026-10-17T18:00:00Z',
      sent_at: '2026-10-17T18:00:00Z',
    }),
    message('m3', {
      direction: 'out',
      body: 'Can you come today?',
      status: 'blocked',
      failed_reason: 'consent_missing',
      created_at: '2026-10-18T14:00:00Z',
    }),
    message('m4', {
      body: 'Is it still there?',
      created_at: '2026-10-18T15:00:00Z',
      received_at: '2026-10-18T15:00:00Z',
    }),
  ],
  ...extra,
});

const customer = {
  id: 'c1',
  first_name: 'Ana',
  last_name: 'Lopez',
  name: 'Ana Lopez',
  source: 'walk_in',
  contacts: [],
  owned_vehicles: [],
  assignments: {sales1: null, sales2: null, bdc: null},
  consent: {text: 'granted', email: 'granted', call: 'granted'},
  created_at: '2026-10-01T00:00:00Z',
  updated_at: '2026-10-01T00:00:00Z',
  preferred_channel: null,
  open_deal: {
    id: 'd1',
    stage: 'engaged',
    days_in_stage: 2,
    vehicle_text: '2022 Honda Civic',
  },
};

describe('ConversationThread', () => {
  beforeEach(() => {
    vi.useFakeTimers({toFake: ['Date']});
    vi.setSystemTime(NOW);
    fetchConversation.mockReset();
    markConversationRead.mockReset();
    simulateInbound.mockReset();
    fetchCustomer.mockReset();
    listTemplates.mockReset();
    simulateInbound.mockRejectedValue(new ApiError(404, 'not_found', 'No'));
    fetchCustomer.mockResolvedValue(customer);
    markConversationRead.mockResolvedValue({});
  });
  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows a skeleton while the conversation loads', () => {
    fetchConversation.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<ConversationThread conversationId="v1" />);
    expect(screen.getByLabelText('Loading conversation')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('groups messages by day, inbound and outbound with their channel', async () => {
    fetchConversation.mockResolvedValue(detail());
    renderWithProviders(<ConversationThread conversationId="v1" />);

    expect(
      await screen.findByRole('heading', {name: 'Ana Lopez'}),
    ).toBeVisible();
    const days = screen.getAllByRole('listitem', {name: /./});
    expect(
      days.map((day) => day.getAttribute('aria-label')),
      'oldest day first, then yesterday and today',
    ).toEqual(['Friday, Oct 9', 'Yesterday', 'Today']);

    const today = within(days[2]!);
    const inbound = today.getByText('Is it still there?');
    expect(inbound.closest('[data-direction]')).toHaveAttribute(
      'data-direction',
      'in',
    );
    const yesterday = within(days[1]!);
    expect(
      yesterday.getByText('Your Civic'),
      'an email shows its subject',
    ).toBeVisible();
    expect(
      yesterday.getByText('Yes, it is here.').closest('[data-direction]'),
    ).toHaveAttribute('data-direction', 'out');
    expect(yesterday.getByText('Email')).toBeVisible();
    expect(yesterday.getByText('Sam Patel')).toBeVisible();
    expect(today.getAllByText('Text').length).toBeGreaterThan(0);
  });

  it('keeps a blocked attempt in the thread and says why it was not sent', async () => {
    fetchConversation.mockResolvedValue(detail());
    renderWithProviders(<ConversationThread conversationId="v1" />);

    expect(
      await screen.findByText('Not sent: no consent was recorded'),
    ).toBeVisible();
    expect(screen.getByText('Can you come today?')).toBeVisible();
  });

  it('marks an unread conversation read when it is opened', async () => {
    const unread = detail();
    unread.conversation.unread_count = 2;
    fetchConversation.mockResolvedValue(unread);
    renderWithProviders(<ConversationThread conversationId="v1" />);

    await waitFor(() =>
      expect(markConversationRead).toHaveBeenCalledWith('v1'),
    );
  });

  it('does not mark a read conversation again', async () => {
    fetchConversation.mockResolvedValue(detail());
    renderWithProviders(<ConversationThread conversationId="v1" />);
    await screen.findByRole('heading', {name: 'Ana Lopez'});
    expect(markConversationRead).not.toHaveBeenCalled();
  });

  it('says so when the conversation is not in the dealership', async () => {
    fetchConversation.mockRejectedValue(
      new ApiError(404, 'not_found', 'Not found'),
    );
    renderWithProviders(<ConversationThread conversationId="nope" />);
    expect(
      await screen.findByText(
        'This conversation does not exist or is not in your dealership.',
      ),
    ).toBeVisible();
  });

  it('inserts a template with the customer, rep, dealership and vehicle filled in', async () => {
    const user = userEvent.setup();
    fetchConversation.mockResolvedValue(detail());
    listTemplates.mockResolvedValue({
      items: [
        {
          id: 't1',
          name: 'First reply',
          channel: 'text',
          body: 'Hi {first_name}, {rep_name} from {dealership} here about the {vehicle}.',
          active: true,
          created_at: '2026-10-01T00:00:00Z',
          updated_at: '2026-10-01T00:00:00Z',
        },
      ],
    });
    renderWithProviders(<ConversationThread conversationId="v1" />);
    await screen.findByRole('heading', {name: 'Ana Lopez'});
    await waitFor(() => expect(fetchCustomer).toHaveBeenCalledWith('c1'));

    await user.click(screen.getByRole('button', {name: 'Templates'}));
    await user.click(await screen.findByRole('button', {name: /First reply/}));

    expect(listTemplates).toHaveBeenCalledWith({channel: 'text', active: true});
    expect(screen.getByLabelText('Message')).toHaveValue(
      'Hi Ana, Sam Patel from Mustang Motors here about the 2022 Honda Civic.',
    );
  });
});
