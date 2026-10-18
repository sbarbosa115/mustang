import {fireEvent, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Appointment} from '@/entities/appointment';
import {ApiError} from '@/shared/api';
import {renderWithProviders} from '@/shared/test/render';
import {BookAppointmentForm} from './BookAppointmentForm';

const bookAppointment = vi.hoisted(() => vi.fn());
const listCustomers = vi.hoisted(() => vi.fn());
vi.mock('@/entities/appointment', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/appointment')>()),
  bookAppointment,
}));
vi.mock('@/entities/customer', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/customer')>()),
  listCustomers,
}));
vi.mock('@/entities/user', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/entities/user')>()),
  listUsers: () =>
    Promise.resolve({
      items: [
        {id: 'u1', name: 'Sam Sales', active: true},
        {id: 'u2', name: 'Sue Seller', active: true},
        {id: 'u3', name: 'Gone Rep', active: false},
      ],
    }),
}));

const FUTURE = '2030-05-20T10:00';
const booked: Appointment = {
  id: 'a1',
  customer: {id: 'c1', name: 'Lauren Lopez'},
  user: {id: 'u1', name: 'Sam Sales'},
  scheduled_at: '2030-05-20T14:00:00+00:00',
  ends_at: '2030-05-20T15:00:00+00:00',
  duration_minutes: 60,
  kind: 'visit',
  status: 'scheduled',
  deal_id: null,
  confirmed_at: null,
  shown_at: null,
  note: null,
};

function renderForm(props: {customer?: {id: string; name: string}} = {}) {
  const onDone = vi.fn();
  renderWithProviders(
    <BookAppointmentForm
      customer={{id: 'c1', name: 'Lauren Lopez'}}
      {...props}
      defaultUserId="u1"
      timeZone="America/New_York"
      onDone={onDone}
    />,
  );
  return {onDone};
}

const typeWhen = (value: string) =>
  fireEvent.change(screen.getByLabelText('When'), {target: {value}});

describe('BookAppointmentForm', () => {
  beforeEach(() => {
    // A block, not an expression: Vitest runs a function returned by beforeEach as the teardown.
    bookAppointment.mockReset();
    listCustomers.mockReset();
  });

  it('names the customer, starts with the rep and an hour, and waits for a time', () => {
    renderForm();

    expect(screen.getByText('Appointment for Lauren Lopez')).toBeVisible();
    expect(
      screen.getByText("In the dealership's time (America/New_York)."),
    ).toBeVisible();
    expect(screen.getByLabelText('Length')).toHaveTextContent('60 min');
    expect(screen.getByLabelText('Kind')).toHaveTextContent('Visit');
    expect(screen.getByRole('button', {name: 'Book'})).toBeDisabled();
  });

  it('books with the time as typed, in the dealership time, and closes', async () => {
    const user = userEvent.setup();
    bookAppointment.mockResolvedValue(booked);
    const {onDone} = renderForm();

    typeWhen(FUTURE);
    await user.type(screen.getByLabelText('Note'), '  Wants the red one ');
    await user.click(screen.getByRole('button', {name: 'Book'}));

    await waitFor(() =>
      expect(bookAppointment).toHaveBeenCalledWith({
        customer_id: 'c1',
        user_id: 'u1',
        deal_id: null,
        scheduled_at: FUTURE,
        duration_minutes: 60,
        kind: 'visit',
        note: 'Wants the red one',
      }),
    );
    await waitFor(() => expect(onDone).toHaveBeenCalled());
  });

  it('does not send a time that has passed', async () => {
    renderForm();

    typeWhen('2020-01-01T10:00');

    expect(
      await screen.findByText('That time has already passed.'),
    ).toBeVisible();
    expect(screen.getByRole('button', {name: 'Book'})).toBeDisabled();
    expect(bookAppointment).not.toHaveBeenCalled();
  });

  it('searches for the customer when none is given, and asks for one before booking', async () => {
    const user = userEvent.setup();
    listCustomers.mockResolvedValue({
      items: [
        {
          id: 'c1',
          name: 'Lauren Lopez',
          primary_phone: '+18135551011',
          primary_email: null,
        },
      ],
      total: 1,
      page: 1,
      per_page: 6,
    });
    bookAppointment.mockResolvedValue(booked);
    // No customer prop: the form searches.
    const onDone = vi.fn();
    renderWithProviders(
      <BookAppointmentForm
        defaultUserId="u1"
        timeZone="America/New_York"
        onDone={onDone}
      />,
    );

    typeWhen(FUTURE);
    await user.click(screen.getByRole('button', {name: 'Book'}));
    expect(await screen.findByText('Choose the customer.')).toBeVisible();
    expect(bookAppointment).not.toHaveBeenCalled();

    await user.type(screen.getByLabelText('Customer'), 'Lau');
    await user.click(
      await screen.findByRole('button', {name: 'Choose Lauren Lopez'}),
    );
    expect(screen.getByText('Appointment for Lauren Lopez')).toBeVisible();
    await user.click(screen.getByRole('button', {name: 'Book'}));

    await waitFor(() =>
      expect(bookAppointment).toHaveBeenCalledWith(
        expect.objectContaining({customer_id: 'c1', scheduled_at: FUTURE}),
      ),
    );
    expect(listCustomers).toHaveBeenCalledWith({q: 'Lau', per_page: 6});
  });

  it('puts an overlap refusal under the time and stays open', async () => {
    const user = userEvent.setup();
    bookAppointment.mockImplementation(() =>
      Promise.reject(
        new ApiError(409, 'appointment_overlap', 'The rep is busy.'),
      ),
    );
    const {onDone} = renderForm();

    typeWhen(FUTURE);
    await user.click(screen.getByRole('button', {name: 'Book'}));

    expect(
      await screen.findByText(
        'That rep already has an appointment at that time.',
      ),
    ).toBeVisible();
    expect(onDone).not.toHaveBeenCalled();
  });

  it('shows any other refusal in words, not as a code', async () => {
    const user = userEvent.setup();
    bookAppointment.mockImplementation(() =>
      Promise.reject(new ApiError(500, 'boom', 'Exploded')),
    );
    renderForm();

    typeWhen(FUTURE);
    await user.click(screen.getByRole('button', {name: 'Book'}));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Something went wrong. Try again.',
    );
  });
});
