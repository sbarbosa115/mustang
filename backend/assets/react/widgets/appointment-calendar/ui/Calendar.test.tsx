import {screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Appointment} from '@/entities/appointment';
import {renderWithProviders} from '@/shared/test/render';
import {AppointmentCalendar} from './AppointmentCalendar';

const listAppointments = vi.hoisted(() => vi.fn());
const cancelAppointment = vi.hoisted(() => vi.fn());
const confirmAppointment = vi.hoisted(() => vi.fn());
vi.mock('@/entities/appointment', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/appointment')>()),
  listAppointments,
  cancelAppointment,
  confirmAppointment,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  useAuth: () => ({me: {dealership: {timezone: 'America/New_York'}}}),
  listUsers: () => Promise.resolve({items: []}),
}));

function appointment(overrides: Partial<Appointment>): Appointment {
  return {
    id: 'a1',
    customer: {id: 'c1', name: 'Lauren Lopez'},
    user: {id: 'u1', name: 'Sam Sales'},
    // 3:30 PM at the dealership (EDT), Wednesday Oct 21
    scheduled_at: '2026-10-21T19:30:00+00:00',
    ends_at: '2026-10-21T20:30:00+00:00',
    duration_minutes: 60,
    kind: 'visit',
    status: 'scheduled',
    deal_id: null,
    confirmed_at: null,
    shown_at: null,
    note: null,
    ...overrides,
  };
}

const WEEK = '/appointments?date=2026-10-21';

function renderCalendar(route = WEEK) {
  return renderWithProviders(
    <AppointmentCalendar userId="u1" timeZone="America/New_York" />,
    {route},
  );
}

describe('AppointmentCalendar', () => {
  beforeEach(() => {
    // A block, not an expression: Vitest runs a function returned by beforeEach as the teardown.
    listAppointments.mockReset();
    cancelAppointment.mockReset();
    confirmAppointment.mockReset();
  });

  it('shows the week from Monday to Sunday and puts each appointment on its day at the dealership time', async () => {
    listAppointments.mockResolvedValue({
      items: [
        appointment({id: 'a1'}),
        // 11:30 PM Wednesday at the dealership, which is already Thursday in UTC
        appointment({
          id: 'a2',
          customer: {id: 'c2', name: 'Diego Clark'},
          scheduled_at: '2026-10-22T03:30:00+00:00',
        }),
        appointment({
          id: 'a3',
          customer: {id: 'c3', name: 'Mia Park'},
          scheduled_at: '2026-10-24T14:00:00+00:00',
        }),
      ],
    });

    renderCalendar();

    expect(await screen.findByText('Mia Park')).toBeInTheDocument();
    expect(
      screen.getByRole('heading', {name: 'Oct 19 – Oct 25, 2026'}),
    ).toBeInTheDocument();
    const days = screen
      .getAllByRole('heading', {level: 3})
      .map((h) => h.textContent);
    expect(days.map((d) => d?.slice(0, 11))).toEqual([
      'Mon, Oct 19',
      'Tue, Oct 20',
      'Wed, Oct 21',
      'Thu, Oct 22',
      'Fri, Oct 23',
      'Sat, Oct 24',
      'Sun, Oct 25',
    ]);
    const wednesday = screen.getByRole('region', {name: 'Wed, Oct 21'});
    expect(within(wednesday).getByText('Lauren Lopez')).toBeInTheDocument();
    expect(within(wednesday).getByText(/3:30 PM/)).toBeInTheDocument();
    expect(
      within(wednesday).getByText('Diego Clark'),
      'Thursday 03:30 UTC is still Wednesday night in Tampa.',
    ).toBeInTheDocument();
    expect(within(wednesday).getByText(/11:30 PM/)).toBeInTheDocument();
    const saturday = screen.getByRole('region', {name: 'Sat, Oct 24'});
    expect(within(saturday).getByText('Mia Park')).toBeInTheDocument();
    expect(
      within(screen.getByRole('region', {name: 'Thu, Oct 22'})).getByText(
        'Nothing booked',
      ),
    ).toBeInTheDocument();
    expect(
      within(wednesday).getByText('2 appointments'),
      'The day heading counts its appointments.',
    ).toBeInTheDocument();
  });

  it('asks for the period in dates at the dealership, and for one rep when Mine is picked', async () => {
    const user = userEvent.setup();
    listAppointments.mockResolvedValue({items: [appointment({})]});
    renderCalendar();
    await screen.findByText('Lauren Lopez');
    expect(listAppointments).toHaveBeenLastCalledWith({
      from: '2026-10-19',
      to: '2026-10-26',
    });

    await user.click(screen.getByRole('radio', {name: 'Mine'}));

    await waitFor(() =>
      expect(listAppointments).toHaveBeenLastCalledWith({
        from: '2026-10-19',
        to: '2026-10-26',
        user_id: 'u1',
      }),
    );
  });

  it('shows one day in the day view', async () => {
    listAppointments.mockResolvedValue({items: [appointment({})]});

    renderCalendar('/appointments?date=2026-10-21&view=day');

    expect(await screen.findByText('Lauren Lopez')).toBeInTheDocument();
    expect(
      screen.getByRole('heading', {level: 2, name: 'Wed, Oct 21'}),
    ).toBeInTheDocument();
    expect(screen.getAllByRole('region')).toHaveLength(1);
    expect(listAppointments).toHaveBeenLastCalledWith({
      from: '2026-10-21',
      to: '2026-10-22',
    });
  });

  it('steps by a week or a day and back to today', async () => {
    const user = userEvent.setup();
    listAppointments.mockResolvedValue({items: [appointment({})]});
    renderCalendar();
    await screen.findByText('Lauren Lopez');

    await user.click(screen.getByRole('button', {name: 'Next week'}));
    await waitFor(() =>
      expect(listAppointments).toHaveBeenLastCalledWith({
        from: '2026-10-26',
        to: '2026-11-02',
      }),
    );

    await user.click(screen.getByRole('button', {name: 'Previous week'}));
    await waitFor(() =>
      expect(listAppointments).toHaveBeenLastCalledWith({
        from: '2026-10-19',
        to: '2026-10-26',
      }),
    );

    await user.click(screen.getByRole('radio', {name: 'Day'}));
    await user.click(await screen.findByRole('button', {name: 'Next day'}));
    await waitFor(() =>
      expect(listAppointments).toHaveBeenLastCalledWith({
        from: '2026-10-22',
        to: '2026-10-23',
      }),
    );
  });

  it('does not show cancelled appointments', async () => {
    listAppointments.mockResolvedValue({
      items: [
        appointment({id: 'a1'}),
        appointment({
          id: 'a2',
          customer: {id: 'c2', name: 'Diego Clark'},
          status: 'cancelled',
          scheduled_at: '2026-10-21T15:00:00+00:00',
        }),
      ],
    });

    renderCalendar();

    expect(await screen.findByText('Lauren Lopez')).toBeInTheDocument();
    expect(screen.queryByText('Diego Clark')).toBeNull();
  });

  it('offers to confirm, show or miss an open appointment, and nothing once it is over', async () => {
    listAppointments.mockResolvedValue({
      items: [
        appointment({id: 'a1', status: 'scheduled'}),
        appointment({
          id: 'a2',
          customer: {id: 'c2', name: 'Diego Clark'},
          status: 'confirmed',
          scheduled_at: '2026-10-21T15:00:00+00:00',
        }),
        appointment({
          id: 'a3',
          customer: {id: 'c3', name: 'Mia Park'},
          status: 'shown',
          scheduled_at: '2026-10-21T13:00:00+00:00',
        }),
      ],
    });

    renderCalendar();

    const lauren = await screen.findByRole('article', {
      name: 'Appointment with Lauren Lopez',
    });
    for (const name of [
      'Confirm the appointment with Lauren Lopez',
      'Lauren Lopez showed',
      'Lauren Lopez did not show',
    ]) {
      expect(within(lauren).getByRole('button', {name})).toBeInTheDocument();
    }
    expect(
      within(lauren).getByRole('button', {name: 'Send confirmation'}),
    ).toBeInTheDocument();
    const diego = screen.getByRole('article', {
      name: 'Appointment with Diego Clark',
    });
    expect(
      within(diego).queryByRole('button', {name: /^Confirm the/}),
      'A confirmed appointment cannot be confirmed again.',
    ).toBeNull();
    expect(
      within(diego).getByRole('button', {name: 'Diego Clark showed'}),
    ).toBeVisible();
    const mia = screen.getByRole('article', {
      name: 'Appointment with Mia Park',
    });
    expect(within(mia).queryAllByRole('button')).toHaveLength(0);
  });

  it('confirms an appointment from its card', async () => {
    const user = userEvent.setup();
    listAppointments.mockResolvedValue({items: [appointment({})]});
    confirmAppointment.mockResolvedValue(appointment({status: 'confirmed'}));
    renderCalendar();

    await user.click(
      await screen.findByRole('button', {
        name: 'Confirm the appointment with Lauren Lopez',
      }),
    );

    await waitFor(() => expect(confirmAppointment).toHaveBeenCalledWith('a1'));
  });

  it('asks before cancelling and names the customer', async () => {
    const user = userEvent.setup();
    listAppointments.mockResolvedValue({items: [appointment({})]});
    cancelAppointment.mockResolvedValue(appointment({status: 'cancelled'}));
    renderCalendar();

    await user.click(
      await screen.findByRole('button', {
        name: 'More actions for Lauren Lopez',
      }),
    );
    await user.click(
      await screen.findByRole('menuitem', {name: 'Cancel appointment'}),
    );

    const dialog = await screen.findByRole('alertdialog', {
      name: 'Cancel the appointment with Lauren Lopez?',
    });
    expect(cancelAppointment).not.toHaveBeenCalled();
    await user.click(
      within(dialog).getByRole('button', {name: 'Cancel appointment'}),
    );
    await waitFor(() => expect(cancelAppointment).toHaveBeenCalledWith('a1'));
  });

  it('shows a skeleton while loading', () => {
    listAppointments.mockReturnValue(new Promise(() => undefined));

    renderCalendar();

    expect(screen.getByLabelText('Loading appointments')).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('says so when the list cannot be loaded, and retries', async () => {
    const user = userEvent.setup();
    listAppointments.mockRejectedValueOnce(new Error('down'));
    listAppointments.mockResolvedValue({items: [appointment({})]});
    renderCalendar();

    expect(
      await screen.findByText('The appointments could not be loaded.'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Try again'}));

    expect(await screen.findByText('Lauren Lopez')).toBeInTheDocument();
  });

  it('says there is nothing booked when the period is empty', async () => {
    listAppointments.mockResolvedValue({items: []});

    renderCalendar();

    expect(
      await screen.findByText('No appointments in this period'),
    ).toBeInTheDocument();
  });

  it('offers a way back when Mine filters everything out', async () => {
    const user = userEvent.setup();
    listAppointments.mockResolvedValue({items: []});
    renderCalendar(`${WEEK}&scope=mine`);

    expect(
      await screen.findByText('None of your appointments are in this period'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Show all'}));

    await waitFor(() =>
      expect(listAppointments).toHaveBeenLastCalledWith({
        from: '2026-10-19',
        to: '2026-10-26',
      }),
    );
  });
});
