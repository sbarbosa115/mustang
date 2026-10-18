import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {ContactNowList} from './ContactNowList';

const fetchContactNow = vi.hoisted(() => vi.fn());
const fetchCustomerConversation = vi.hoisted(() => vi.fn());
vi.mock('@/entities/worklist-item', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/worklist-item')>()),
  fetchContactNow,
}));
vi.mock('@/entities/conversation', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/conversation')>()),
  fetchCustomerConversation,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({
    me: {
      user: {id: 'u1', name: 'Sam Patel', role: 'sales'},
      dealership: {name: 'Mustang Motors', timezone: 'America/New_York'},
      roles: ['sales'],
    },
  }),
}));

const minutesAgo = (m: number) =>
  new Date(Date.now() - m * 60_000).toISOString();
const consent = {text: 'granted', email: 'missing', call: 'granted'};
const item = (id: string, name: string, extra: object) => ({
  customer: {id, name},
  score: 100,
  hot: false,
  channel_availability: consent,
  suggested_channel: 'text',
  deal: null,
  appointment: null,
  since: null,
  phone: '+18135551002',
  ...extra,
});
const items = [
  item('c1', 'Sarah Johnson', {
    reason_code: 'unanswered_inbound',
    reason_params: {minutes: 47, channel: 'text', question: true},
    score: 167,
    hot: true,
    since: minutesAgo(47),
    deal: {id: 'd1', stage: 'engaged', days_in_stage: 6, vehicle_text: null},
  }),
  item('c2', 'Marcus Lee', {
    reason_code: 'new_lead_uncontacted',
    reason_params: {minutes: 20, source: 'website', vehicle: '2024 Camry'},
    since: minutesAgo(20),
  }),
  item('c3', 'Ana Ruiz', {
    reason_code: 'appointment_today_unconfirmed',
    reason_params: {
      scheduled_at: '2026-10-20T20:30:00+00:00',
      kind: 'test_drive',
    },
    appointment: {
      id: 'a1',
      scheduled_at: '2026-10-20T20:30:00+00:00',
      status: 'scheduled',
    },
  }),
  item('c4', 'Tom Baker', {
    reason_code: 'no_show_without_follow_up',
    reason_params: {scheduled_at: '2026-10-20T13:00:00+00:00', kind: 'visit'},
  }),
  item('c5', 'Lin Chen', {
    reason_code: 'deal_idle',
    reason_params: {days: 4, stage: 'proposal', threshold: 1},
    channel_availability: {
      text: 'opted_out',
      email: 'missing',
      call: 'missing',
    },
    suggested_channel: 'call',
  }),
];
const list = (rows: unknown[]) => ({
  user: {id: 'u1', name: 'Sam Patel'},
  items: rows,
  total: rows.length,
});

function renderList(props = {}) {
  return renderWithProviders(
    <Routes>
      <Route path="/today" element={<ContactNowList {...props} />} />
      <Route path="/inbox/:id" element={<p>Thread opened</p>} />
    </Routes>,
    {route: '/today'},
  );
}

/** The n-th row; fails the test when there are fewer. */
function rowAt(rows: HTMLElement[], n: number): HTMLElement {
  const row = rows[n];
  if (!row) throw new Error(`No row ${n}`);
  return row;
}

describe('ContactNowList', () => {
  beforeEach(() => {
    fetchContactNow.mockReset();
    fetchCustomerConversation.mockReset();
    window.localStorage.clear();
  });

  it('shows skeletons while the list loads', () => {
    fetchContactNow.mockReturnValue(new Promise(() => undefined));
    renderList();
    expect(screen.getByLabelText('Loading')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('words each reason from its code, most urgent first, hot only where it waits', async () => {
    fetchContactNow.mockResolvedValue(list(items));
    renderList();

    const table = await screen.findByRole('table');
    const rows = within(table).getAllByRole('article');
    expect(rows.map((r) => within(r).getByRole('link').textContent)).toEqual([
      'Sarah Johnson',
      'Marcus Lee',
      'Ana Ruiz',
      'Tom Baker',
      'Lin Chen',
    ]);
    expect(
      within(rowAt(rows, 0)).getByText(
        'Replied 47 min ago, nobody answered; question unanswered',
      ),
    ).toBeInTheDocument();
    expect(within(rowAt(rows, 0)).getByText('Contact now')).toBeInTheDocument();
    expect(within(rowAt(rows, 0)).getByText(/Engaged/)).toBeInTheDocument();
    expect(
      within(rowAt(rows, 1)).getByText(
        'New lead for a 2024 Camry 20 min ago, not contacted yet',
      ),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 2)).getByText(
        'Today at 4:30 PM: test drive not confirmed',
      ),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 3)).getByText(
        'Missed the visit at 9:00 AM, no follow-up yet',
      ),
    ).toBeInTheDocument();
    expect(
      within(rowAt(rows, 4)).getByText('No activity for 4 days in proposal'),
    ).toBeInTheDocument();
    expect(within(table).getAllByText('Contact now')).toHaveLength(1);
    expect(screen.getByText('5 customers to contact')).toBeInTheDocument();
    expect(screen.getByText('1 waiting more than 30 min')).toBeInTheDocument();
  });

  it('disables a channel without consent and says why', async () => {
    fetchContactNow.mockResolvedValue(list([items[4]]));
    renderList();

    const row = within(await screen.findByRole('table')).getByRole('article');
    expect(
      within(row).getByRole('button', {
        name: 'Send a text: This contact opted out of Text',
      }),
    ).toBeDisabled();
    expect(
      within(row).getByRole('button', {
        name: 'Call: No consent recorded for Call',
      }),
    ).toBeDisabled();
  });

  it("opens the customer's inbox thread when text is tapped", async () => {
    fetchContactNow.mockResolvedValue(list([items[0]]));
    fetchCustomerConversation.mockResolvedValue({conversation: {id: 'conv-9'}});
    renderList();

    const table = await screen.findByRole('table');
    await userEvent.click(
      within(table).getByRole('button', {name: 'Send a text'}),
    );

    expect(fetchCustomerConversation).toHaveBeenCalledWith('c1');
    expect(await screen.findByText('Thread opened')).toBeInTheDocument();
  });

  it("asks for another rep's list when given one", async () => {
    fetchContactNow.mockResolvedValue(list([]));
    renderList({userId: 'u2'});

    await waitFor(() =>
      expect(fetchContactNow).toHaveBeenCalledWith({user_id: 'u2'}),
    );
  });

  it('says nobody needs you when the list is empty', async () => {
    fetchContactNow.mockResolvedValue(list([]));
    renderList();

    expect(
      await screen.findByText(/Nobody needs you right now/),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Open the inbox'})).toHaveAttribute(
      'href',
      '/inbox',
    );
  });

  it('counts customers seen today who left the list as done', async () => {
    const today = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/New_York',
    }).format(new Date());
    window.localStorage.setItem(
      `mustang.today.seen.u1.${today}`,
      JSON.stringify(['c1', 'gone']),
    );
    fetchContactNow.mockResolvedValue(list([items[0], items[1]]));
    renderList();

    expect(await screen.findByText('1 of 3 done today')).toBeInTheDocument();
    expect(
      screen.getByRole('progressbar', {name: "Today's progress"}),
    ).toHaveAttribute('aria-valuenow', '1');
  });

  it('shows an error with a way to try again', async () => {
    fetchContactNow.mockRejectedValue(new Error('boom'));
    renderList();

    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeInTheDocument();
  });
});
