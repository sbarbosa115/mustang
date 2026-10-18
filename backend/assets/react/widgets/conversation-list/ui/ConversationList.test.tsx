import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {ConversationList} from './ConversationList';

const listConversations = vi.hoisted(() => vi.fn());
const fetchConversationCounts = vi.hoisted(() => vi.fn());
const listUsers = vi.hoisted(() => vi.fn());
vi.mock('@/entities/conversation', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/conversation')>()),
  listConversations,
  fetchConversationCounts,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  listUsers,
  useAuth: () => ({
    me: {
      user: {id: 'u1', name: 'Sam Patel', role: 'sales'},
      dealership: {name: 'Mustang Motors', timezone: 'America/New_York'},
    },
  }),
}));

const conversation = (id: string, name: string, extra = {}) => ({
  id,
  customer: {id: `c-${id}`, name},
  unread_count: 0,
  unreplied: false,
  fumbled: false,
  consent: {text: 'granted', email: 'missing', call: 'missing'},
  assignee: {id: 'u1', name: 'Sam Patel'},
  last_message: {
    id: `m-${id}`,
    conversation_id: id,
    direction: 'out',
    channel: 'text',
    body: 'See you at 3.',
    status: 'sent',
    to_value: '+18135551001',
    from_value: '+18135550100',
    created_at: new Date().toISOString(),
  },
  last_message_at: new Date().toISOString(),
  ...extra,
});
const page = (items: unknown[]) => ({
  items,
  total: items.length,
  page: 1,
  per_page: 25,
});
const counts = {
  all: 12,
  unreplied: 3,
  fumbled: 1,
  by_user: [{user: {id: 'u2', name: 'Jordan Reyes'}, count: 2}],
};

describe('ConversationList', () => {
  beforeEach(() => {
    listConversations.mockReset();
    fetchConversationCounts.mockReset();
    listUsers.mockReset();
    fetchConversationCounts.mockResolvedValue(counts);
    listUsers.mockResolvedValue({
      items: [
        {id: 'u1', name: 'Sam Patel', role: 'sales', active: true},
        {id: 'u2', name: 'Jordan Reyes', role: 'sales', active: true},
        {id: 'u9', name: 'Ada Admin', role: 'admin', active: true},
      ],
      total: 3,
      page: 1,
      per_page: 100,
    });
  });

  it('shows skeletons while the first page loads', () => {
    listConversations.mockReturnValue(new Promise(() => undefined));
    renderWithProviders(<ConversationList />, {route: '/inbox'});
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('shows each folder with its count, and a folder narrows the list', async () => {
    const user = userEvent.setup();
    listConversations.mockResolvedValue(page([conversation('1', 'Ana')]));
    renderWithProviders(<ConversationList />, {route: '/inbox'});

    const folders = screen.getByRole('group', {name: 'Folders'});
    const all = await within(folders).findByRole('button', {name: /All/});
    expect(all, 'the current folder is pressed').toHaveAttribute(
      'aria-pressed',
      'true',
    );
    await waitFor(() => expect(all).toHaveTextContent('12'));
    expect(
      within(folders).getByRole('button', {name: /Unreplied/}),
    ).toHaveTextContent('3');
    expect(
      within(folders).getByRole('button', {name: /Fumbled/}),
    ).toHaveTextContent('1');

    await user.click(within(folders).getByRole('button', {name: /Unreplied/}));
    await waitFor(() =>
      expect(listConversations).toHaveBeenLastCalledWith(
        expect.objectContaining({folder: 'unreplied'}),
      ),
    );
    expect(
      within(folders).getByRole('button', {name: /Unreplied/}),
    ).toHaveAttribute('aria-pressed', 'true');
  });

  it('lists each conversation with its last message, unread count and an unreplied marker', async () => {
    listConversations.mockResolvedValue(
      page([
        conversation('1', 'Ana Lopez', {
          unread_count: 2,
          unreplied: true,
          fumbled: true,
          last_message: {
            ...conversation('1', 'Ana Lopez').last_message,
            direction: 'in',
            body: 'Is the Civic still there?',
          },
        }),
        conversation('2', 'Ben Ortiz'),
        conversation('3', 'Cara Diaz', {unreplied: true}),
      ]),
    );
    renderWithProviders(<ConversationList selectedId="2" />, {
      route: '/inbox',
    });

    const ana = (await screen.findAllByRole('link', {name: /Ana Lopez/}))[0]!;
    expect(ana).toHaveAttribute('href', '/inbox/1');
    expect(ana).toHaveTextContent('Is the Civic still there?');
    expect(ana).toHaveTextContent('2 unread');
    expect(ana, 'never colour alone: the word is there').toHaveTextContent(
      'Fumbled',
    );
    expect(
      screen.getAllByRole('link', {name: /Cara Diaz/})[0],
    ).toHaveTextContent('Unreplied');
    const ben = screen.getAllByRole('link', {name: /Ben Ortiz/})[0]!;
    expect(ben, 'our own last message reads "You:"').toHaveTextContent(
      'You: See you at 3.',
    );
    expect(ben).toHaveAttribute('aria-current', 'page');
  });

  it('says there are no conversations yet when the inbox is empty', async () => {
    listConversations.mockResolvedValue(page([]));
    renderWithProviders(<ConversationList />, {route: '/inbox'});
    expect(
      await screen.findByText(
        'No conversations yet. They start when you text or email a customer, or when one writes in.',
      ),
    ).toBeVisible();
  });

  it('says nobody is waiting when the unreplied folder is empty', async () => {
    listConversations.mockResolvedValue(page([]));
    renderWithProviders(<ConversationList />, {
      route: '/inbox?folder=unreplied',
    });
    expect(
      await screen.findByText('Nobody is waiting on a reply.'),
    ).toBeVisible();
  });

  it('filters by rep with their unreplied count, and Show all clears every filter', async () => {
    const user = userEvent.setup();
    listConversations.mockImplementation((query: {user_id?: string}) =>
      Promise.resolve(
        query.user_id ? page([]) : page([conversation('1', 'Ana Lopez')]),
      ),
    );
    renderWithProviders(<ConversationList />, {
      route: '/inbox?folder=fumbled',
    });
    await screen.findAllByRole('link', {name: /Ana Lopez/});

    await user.click(screen.getByRole('combobox', {name: 'Assigned rep'}));
    expect(
      screen.queryByRole('option', {name: /Ada Admin/}),
      'admins are not reps',
    ).toBeNull();
    await user.click(
      await screen.findByRole('option', {name: 'Jordan Reyes · 2 unreplied'}),
    );
    expect(
      await screen.findByText('Nothing matches these filters.'),
    ).toBeVisible();
    expect(listConversations).toHaveBeenLastCalledWith(
      expect.objectContaining({user_id: 'u2', folder: 'fumbled'}),
    );

    await user.click(screen.getByRole('button', {name: 'Show all'}));
    expect(
      (await screen.findAllByRole('link', {name: /Ana Lopez/})).length,
    ).toBeGreaterThan(0);
    expect(listConversations).toHaveBeenLastCalledWith(
      expect.not.objectContaining({user_id: 'u2'}),
    );
    expect(
      screen.getByRole('button', {name: /All/}),
      'Show all goes back to every folder',
    ).toHaveAttribute('aria-pressed', 'true');
  });

  it('offers a retry when the list cannot be loaded', async () => {
    const user = userEvent.setup();
    listConversations.mockImplementationOnce(() =>
      Promise.reject(new Error('down')),
    );
    listConversations.mockResolvedValue(page([conversation('1', 'Ana Lopez')]));
    renderWithProviders(<ConversationList />, {route: '/inbox'});

    await user.click(await screen.findByRole('button', {name: 'Try again'}));
    expect(
      (await screen.findAllByRole('link', {name: /Ana Lopez/})).length,
    ).toBeGreaterThan(0);
  });
});
