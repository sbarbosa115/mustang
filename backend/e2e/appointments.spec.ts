import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * APP-01 – 06. A case that changes data creates its own customer (named after the case) and books in a slot of its own:
 * a random day well ahead, so the seeded appointments and earlier runs never clash with it. The rep is whoever signs in.
 */

function origin(page: Page): Record<string, string> {
  // State-changing requests must come from the app's own origin (the page is open on it).
  return {Origin: new URL(page.url()).origin};
}

async function customer(page: Page, name: string, phone: string) {
  const response = await page.request.post('/api/v1/customers', {
    headers: origin(page),
    data: {first_name: name, last_name: 'Test', phone},
  });
  expect(response.status(), `creating ${name}`).toBe(201);
  return ((await response.json()) as {id: string}).id;
}

/** A free day well ahead (45 to 700 days) and an hour of the working day, as the form takes them. */
function slot(): {date: string; time: string; local: string} {
  const day = new Date(Date.now() + (45 + Math.random() * 655) * 86_400_000);
  const date = day.toISOString().slice(0, 10);
  const time = `${String(8 + Math.floor(Math.random() * 10)).padStart(2, '0')}:00`;
  return {date, time, local: `${date}T${time}`};
}

/** "Tue, Oct 20": how the calendar names a day. */
function dayName(date: string): string {
  return new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(`${date}T12:00:00Z`));
}

async function book(page: Page, customerId: string, local: string) {
  const response = await page.request.post('/api/v1/appointments', {
    headers: origin(page),
    data: {customer_id: customerId, scheduled_at: local},
  });
  expect(response.status(), `booking ${local}`).toBe(201);
  return (await response.json()) as {id: string};
}

test('APP-01 · the calendar shows a week or a day at the dealership time', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/appointments');
  const when = slot();
  const id = await customer(page, 'APP-01', '(813) 555-9701');
  await book(page, id, when.local);

  await page.goto(`/appointments?date=${when.date}`);

  await expect(
    page.getByRole('heading', {level: 1, name: 'Appointments'}),
  ).toBeVisible();
  const day = page.getByRole('region', {name: dayName(when.date)});
  await expect(day.getByText('APP-01 Test')).toBeVisible();
  await expect(day.getByText('Scheduled')).toBeVisible();
  await expect(day.getByText('with Sam Patel')).toBeVisible();
  const calendar = page.getByRole('group', {name: 'Appointments calendar'});
  await expect(calendar.getByRole('region')).toHaveCount(7);

  await page.getByText('Day', {exact: true}).click();
  await expect(calendar.getByRole('region')).toHaveCount(1);
  await expect(page.getByText('APP-01 Test')).toBeVisible();
  await page.getByRole('button', {name: 'Next day'}).click();
  await expect(page.getByText('APP-01 Test')).toHaveCount(0);
  await page.getByRole('button', {name: 'Previous day'}).click();
  await expect(page.getByText('APP-01 Test')).toBeVisible();

  await page.getByText('Mine', {exact: true}).click();
  await expect(page.getByText('APP-01 Test')).toBeVisible();
  expect(errors).toEqual([]);
});

test('APP-02 · book an appointment, and the refusals', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  await page.goto('/appointments');
  const when = slot();
  await customer(page, 'APP-02', '(813) 555-9702');
  await customer(page, 'APP-02b', '(813) 555-9712');

  await page.goto(`/appointments?date=${when.date}`);
  await page.getByRole('button', {name: 'Book appointment'}).click();
  let dialog = page.getByRole('dialog', {name: 'Book an appointment'});
  await dialog.getByLabel('Customer').fill('APP-02 ');
  await dialog.getByRole('button', {name: 'Choose APP-02 Test'}).click();
  await dialog.getByLabel('When').fill('2020-01-01T10:00');
  await expect(dialog.getByText('That time has already passed.')).toBeVisible();
  await expect(dialog.getByRole('button', {name: 'Book'})).toBeDisabled();
  await dialog.getByLabel('When').fill(when.local);
  await dialog.getByLabel('Note').fill('Wants to see the red one');
  await dialog.getByRole('button', {name: 'Book'}).click();

  await expect(page.getByText(/^Booked APP-02 Test for /)).toBeVisible();
  const day = page.getByRole('region', {name: dayName(when.date)});
  await expect(day.getByText('APP-02 Test')).toBeVisible();
  await expect(day.getByText('Wants to see the red one')).toBeVisible();

  // The same rep at the same time is refused.
  await page.getByRole('button', {name: 'Book appointment'}).click();
  dialog = page.getByRole('dialog', {name: 'Book an appointment'});
  await dialog.getByLabel('Customer').fill('APP-02b');
  await dialog.getByRole('button', {name: 'Choose APP-02b Test'}).click();
  await dialog.getByLabel('When').fill(when.local);
  await dialog.getByRole('button', {name: 'Book'}).click();
  await expect(
    dialog.getByText('That rep already has an appointment at that time.'),
  ).toBeVisible();
});

test('APP-03 · confirm, then mark the customer as shown', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/appointments');
  const when = slot();
  const id = await customer(page, 'APP-03', '(813) 555-9703');
  const deal = await page.request.post('/api/v1/deals', {
    headers: origin(page),
    data: {customer_id: id},
  });
  expect(deal.status()).toBe(201);
  await book(page, id, when.local);

  await page.goto(`/appointments?date=${when.date}&view=day`);
  const card = page.getByRole('article', {
    name: 'Appointment with APP-03 Test',
  });
  await card.getByRole('button', {name: /^Confirm the appointment/}).click();
  await expect(
    page.getByText("Marked APP-03 Test's appointment as confirmed."),
  ).toBeVisible();
  await expect(card.getByText('Confirmed')).toBeVisible();
  await expect(
    card.getByRole('button', {name: /^Confirm the appointment/}),
  ).toHaveCount(0);

  const stage = async () =>
    (
      (await (await page.request.get(`/api/v1/customers/${id}`)).json()) as {
        open_deal: {stage: string};
      }
    ).open_deal.stage;
  expect(await stage(), 'booking moved the deal').toBe('appointment');

  await card.getByRole('button', {name: 'APP-03 Test showed'}).click();
  await expect(page.getByText('APP-03 Test showed. Nice.')).toBeVisible();
  await expect(card.getByText('Showed', {exact: true})).toBeVisible();
  await expect(card.getByRole('button')).toHaveCount(0);
  await expect.poll(stage, 'showing moved the deal').toBe('visit');
});

test('APP-04 · a no-show frees the rep for that time', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  await page.goto('/appointments');
  const when = slot();
  const id = await customer(page, 'APP-04', '(813) 555-9704');
  await book(page, id, when.local);

  await page.goto(`/appointments?date=${when.date}&view=day`);
  const card = page.getByRole('article', {
    name: 'Appointment with APP-04 Test',
  });
  await card.getByRole('button', {name: 'APP-04 Test did not show'}).click();

  await expect(
    page.getByText('Marked APP-04 Test as a no-show.'),
  ).toBeVisible();
  await expect(card.getByText('No-show', {exact: true})).toBeVisible();
  await expect(card.getByRole('button')).toHaveCount(0);
  const again = await page.request.post('/api/v1/appointments', {
    headers: origin(page),
    data: {customer_id: id, scheduled_at: when.local},
  });
  expect(again.status(), 'the missed slot is free again').toBe(201);
});

test('APP-05 · reschedule, then cancel', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  await page.goto('/appointments');
  const when = slot();
  const later = slot();
  const id = await customer(page, 'APP-05', '(813) 555-9705');
  await book(page, id, when.local);

  await page.goto(`/appointments?date=${when.date}&view=day`);
  let card = page.getByRole('article', {name: 'Appointment with APP-05 Test'});
  await card.getByRole('button', {name: /^Confirm the appointment/}).click();
  await expect(card.getByText('Confirmed')).toBeVisible();

  await card
    .getByRole('button', {name: 'More actions for APP-05 Test'})
    .click();
  await page.getByRole('menuitem', {name: 'Reschedule'}).click();
  const dialog = page.getByRole('dialog', {
    name: 'Reschedule the appointment for APP-05 Test',
  });
  await dialog.getByLabel('New time').fill('2020-01-01T10:00');
  await expect(dialog.getByText('That time has already passed.')).toBeVisible();
  await dialog.getByLabel('New time').fill(later.local);
  await dialog.getByRole('button', {name: 'Reschedule'}).click();
  await expect(page.getByText(/^Moved APP-05 Test to /)).toBeVisible();
  await expect(page.getByText('APP-05 Test')).toHaveCount(0);

  await page.goto(`/appointments?date=${later.date}&view=day`);
  card = page.getByRole('article', {name: 'Appointment with APP-05 Test'});
  await expect(card.getByText('Scheduled')).toBeVisible();
  await card
    .getByRole('button', {name: 'More actions for APP-05 Test'})
    .click();
  await page.getByRole('menuitem', {name: 'Cancel appointment'}).click();
  const confirm = page.getByRole('alertdialog', {
    name: 'Cancel the appointment with APP-05 Test?',
  });
  await confirm.getByRole('button', {name: 'Keep it'}).click();
  await expect(card).toBeVisible();
  await card
    .getByRole('button', {name: 'More actions for APP-05 Test'})
    .click();
  await page.getByRole('menuitem', {name: 'Cancel appointment'}).click();
  await page
    .getByRole('alertdialog')
    .getByRole('button', {name: 'Cancel appointment'})
    .click();

  await expect(
    page.getByText('Cancelled the appointment with APP-05 Test.'),
  ).toBeVisible();
  await expect(page.getByText('APP-05 Test')).toHaveCount(0);
  await page.goto(`/customers/${id}?tab=appointments`);
  await page.getByRole('tab', {name: 'Appointments'}).click();
  await expect(page.getByText('Cancelled')).toBeVisible();
});

test("APP-06 · another dealership's appointments do not exist", async ({
  signedInAs,
}) => {
  const sales = await signedInAs('sales');
  await sales.goto('/appointments');
  const when = slot();
  const id = await customer(sales, 'APP-06', '(813) 555-9706');
  const appointment = await book(sales, id, when.local);

  const page = await signedInAs('other');
  await page.goto('/appointments');
  const own = await page.request.get(
    `/api/v1/appointments?from=${when.date}&to=2999-01-01`,
  );
  expect(((await own.json()) as {items: unknown[]}).items).toEqual([]);
  const get = await page.request.get(`/api/v1/appointments/${appointment.id}`);
  expect(get.status()).toBe(404);
  const confirm = await page.request.post(
    `/api/v1/appointments/${appointment.id}/confirm`,
    {headers: origin(page)},
  );
  expect(confirm.status()).toBe(404);
  await page.goto(`/appointments?date=${when.date}`);
  await expect(page.getByText('APP-06 Test')).toHaveCount(0);
  await expect(page.getByText('No appointments in this period')).toBeVisible();
});
