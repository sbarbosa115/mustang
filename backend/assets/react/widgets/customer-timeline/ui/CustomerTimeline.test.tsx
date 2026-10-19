import {screen, within} from '@testing-library/react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderWithProviders} from '@/shared/test/render';
import {CustomerTimeline} from './CustomerTimeline';

const fetchTimeline = vi.hoisted(() => vi.fn());
vi.mock('@/entities/activity', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/activity')>()),
  fetchTimeline,
}));

const NOW = Date.now();
const iso = (hoursAgo: number) =>
  new Date(NOW - hoursAgo * 3_600_000).toISOString();

function entry(
  id: string,
  code: string | null,
  kind: string,
  hoursAgo: number,
  extra: object = {},
) {
  return {
    id,
    code,
    kind,
    occurred_at: iso(hoursAgo),
    summary: 'Stored English',
    ref_type: null,
    ref_id: null,
    actor: null,
    ...extra,
  };
}

function page(items: object[], total = items.length) {
  return {items, total, page: 1, per_page: 25};
}

describe('CustomerTimeline', () => {
  beforeEach(() => {
    fetchTimeline.mockReset();
  });

  it('shows a skeleton while loading', () => {
    fetchTimeline.mockReturnValue(new Promise(() => {}));
    renderWithProviders(<CustomerTimeline customerId="c1" />);
    expect(screen.getByLabelText('Loading the timeline')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('says so when nothing has happened', async () => {
    fetchTimeline.mockResolvedValue(page([]));
    renderWithProviders(<CustomerTimeline customerId="c1" />);
    expect(
      await screen.findByText('Nothing has happened with this customer yet.'),
    ).toBeVisible();
  });

  it('offers a retry when the timeline cannot be loaded', async () => {
    fetchTimeline.mockRejectedValue(new Error('boom'));
    renderWithProviders(<CustomerTimeline customerId="c1" />);
    expect(
      await screen.findByText('The timeline could not be loaded.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Try again'})).toBeVisible();
  });

  it('groups entries by day, newest first, with the day named', async () => {
    fetchTimeline.mockResolvedValue(
      page([
        entry('a', 'message_received_text', 'message_in', 0.1),
        entry('b', 'note_added', 'note', 0.2),
        entry('c', 'lead_captured', 'lead', 24 * 9),
      ]),
    );
    renderWithProviders(<CustomerTimeline customerId="c1" />);

    const today = await screen.findByRole('region', {name: 'Today'});
    expect(within(today).getAllByRole('listitem')).toHaveLength(2);
    const sections = screen.getAllByRole('region');
    expect(sections).toHaveLength(2);
    expect(
      within(sections[1] as HTMLElement).getByText('Lead received'),
    ).toBeVisible();
  });

  it('words entries through i18n from their code, with the channel and the actor', async () => {
    fetchTimeline.mockResolvedValue(
      page([
        entry('a', 'message_sent_email', 'message_out', 1, {
          actor: {id: 'u1', name: 'Alex Rivera'},
        }),
        entry('b', 'message_received_text', 'message_in', 2),
        entry('c', 'stage_changed_engaged', 'stage', 3, {
          ref_type: 'deal',
          ref_id: 'd1',
        }),
        entry('d', 'consent_revoked_text', 'consent', 4),
      ]),
    );
    renderWithProviders(<CustomerTimeline customerId="c1" />);

    expect(await screen.findByText('Email sent')).toBeVisible();
    expect(screen.getByText('by Alex Rivera')).toBeVisible();
    expect(screen.getByText('Text received')).toBeVisible();
    expect(screen.getByText('Text consent revoked')).toBeVisible();
    expect(
      screen.getByRole('link', {name: 'Deal moved to Engaged'}),
    ).toHaveAttribute('href', '/deals/d1');
    expect(screen.queryByText('Stored English')).not.toBeInTheDocument();
  });

  it('draws a channel icon on message entries and none on the others', async () => {
    fetchTimeline.mockResolvedValue(
      page([
        entry('a', 'message_sent_email', 'message_out', 1),
        entry('b', 'note_added', 'note', 2),
      ]),
    );
    const {container} = renderWithProviders(
      <CustomerTimeline customerId="c1" />,
    );
    await screen.findByText('Email sent');
    expect(container.querySelectorAll('.text-channel-email')).toHaveLength(1);
    expect(container.querySelectorAll('.text-channel-text')).toHaveLength(0);
  });

  it('falls back to the kind and the stored summary for an entry without a code', async () => {
    fetchTimeline.mockResolvedValue(
      page([entry('a', null, 'note', 1, {summary: 'Old row'})]),
    );
    renderWithProviders(<CustomerTimeline customerId="c1" />);
    expect(await screen.findByText('Note added')).toBeVisible();
    expect(screen.getByText('Old row')).toBeVisible();
  });

  it('pages through a long timeline', async () => {
    fetchTimeline.mockResolvedValue(
      page([entry('a', 'note_added', 'note', 1)], 60),
    );
    renderWithProviders(<CustomerTimeline customerId="c1" />);
    await screen.findByText('1–25 of 60');
    expect(screen.getByRole('button', {name: 'Next page'})).toBeEnabled();
  });
});
