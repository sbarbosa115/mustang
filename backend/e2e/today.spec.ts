import type {APIRequestContext, Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * TODAY-01 – 05, on the phone project (390 × 844). Each case adds its own customer, named after the case, with a
 * phone of its own (555-980x), so the seeded customers stay as the seed made them.
 */

function api(page: Page): {
  request: APIRequestContext;
  headers: Record<string, string>;
} {
  // State-changing requests must come from the app's own origin (the page is open on it).
  return {request: page.request, headers: {Origin: new URL(page.url()).origin}};
}

async function myId(page: Page): Promise<string> {
  const me = await page.request.get('/api/v1/me');
  return ((await me.json()) as {user: {id: string}}).user.id;
}

async function userIdOf(page: Page, email: string): Promise<string> {
  const response = await page.request.get('/api/v1/users?per_page=100');
  const users = (await response.json()) as {
    items: Array<{id: string; email: string}>;
  };
  const user = users.items.find((u) => u.email === email);
  expect(user, `${email} is a user`).toBeTruthy();
  return user?.id ?? '';
}

/** A customer of mine (Sales 1) with text consent on their phone. */
async function myCustomer(
  page: Page,
  name: string,
  phone: string,
  owner?: string,
): Promise<string> {
  const {request, headers} = api(page);
  const created = await request.post('/api/v1/customers', {
    headers,
    data: {first_name: name, last_name: 'Test', phone},
  });
  expect(created.status(), `creating ${name}`).toBe(201);
  const id = ((await created.json()) as {id: string}).id;
  const assigned = await request.patch(`/api/v1/customers/${id}/assignments`, {
    headers,
    data: {sales1_user_id: owner ?? (await myId(page))},
  });
  expect(assigned.status(), `assigning ${name}`).toBe(200);
  const consent = await request.post(`/api/v1/customers/${id}/consents`, {
    headers,
    data: {channel: 'text', contact_value: phone, source: 'verbal'},
  });
  expect(consent.status(), `recording text consent for ${name}`).toBe(201);
  return id;
}

async function reply(page: Page, from: string, body: string): Promise<string> {
  const {request, headers} = api(page);
  const response = await request.post('/api/v1/dev/inbound', {
    headers,
    data: {from, body},
  });
  expect(response.status(), `the customer writes ${body}`).toBe(201);
  return ((await response.json()) as {conversation_id: string}).conversation_id;
}

function rowOf(page: Page, name: string) {
  return page.getByRole('article').filter({hasText: name});
}

test('TODAY-01 · an unanswered question is on Contact now with its reason', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/today');
  const errors = consoleErrors(page);
  await myCustomer(page, 'TODAY-01', '+18135559801');
  await reply(page, '(813) 555-9801', 'Is the Civic still there?');

  await page.goto('/today');
  const row = rowOf(page, 'TODAY-01 Test');
  await expect(row).toContainText(
    'Replied less than a minute ago, nobody answered; question unanswered',
  );
  await expect(row.getByRole('button', {name: 'Send a text'})).toBeEnabled();
  await expect(
    row.getByRole('button', {
      name: 'Send an email: No consent recorded for Email',
    }),
  ).toBeDisabled();
  await expect(row.getByText('Contact now')).toHaveCount(0);
  expect(errors).toEqual([]);
});

test("TODAY-02 · text opens the customer's thread; once answered the row is done", async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/today');
  await myCustomer(page, 'TODAY-02', '+18135559802');
  const conversation = await reply(page, '(813) 555-9802', 'Still available?');

  await page.goto('/today');
  await rowOf(page, 'TODAY-02 Test')
    .getByRole('button', {name: 'Send a text'})
    .click();
  await expect(page).toHaveURL(new RegExp(`/inbox/${conversation}`));

  const {request, headers} = api(page);
  const sent = await request.post(
    `/api/v1/conversations/${conversation}/messages`,
    {headers, data: {channel: 'text', body: 'Yes, it is'}},
  );
  expect(sent.status()).toBe(201);

  await page.goto('/today');
  await expect(
    page.getByRole('progressbar', {name: "Today's progress"}),
  ).toBeVisible();
  await expect(rowOf(page, 'TODAY-02 Test')).toHaveCount(0);
  await expect(page.getByText(/[1-9]\d* of \d+ done today/)).toBeVisible();
});

test('TODAY-03 · a new lead nobody contacted is on the list with its vehicle', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/today');
  const {request, headers} = api(page);
  const lead = await request.post('/api/v1/leads', {
    headers,
    data: {
      first_name: 'TODAY-03',
      last_name: 'Test',
      phone: '+18135559803',
      source: 'website',
      vehicle_interest_text: '2024 Camry',
      assigned_user_id: await myId(page),
    },
  });
  expect(lead.status()).toBe(201);

  await page.goto('/today');
  const row = rowOf(page, 'TODAY-03 Test');
  await expect(row).toContainText(
    'New lead for a 2024 Camry less than a minute ago, not contacted yet',
  );
  await expect(row).toContainText('New');
});

test("TODAY-05 · only a manager opens another rep's list", async ({
  signedInAs,
}) => {
  const sales = await signedInAs('sales');
  await sales.goto('/today');
  const sales2 = await userIdOf(sales, 'sales2@mustang.test');
  const refused = await sales.request.get(
    `/api/v1/worklist/contact-now?user_id=${sales2}`,
  );
  expect(refused.status()).toBe(403);
  expect(((await refused.json()) as {error: string}).error).toBe(
    'worklist_not_yours',
  );
  await myCustomer(sales, 'TODAY-05', '+18135559805', sales2);
  await reply(sales, '(813) 555-9805', 'Hello');

  const manager = await signedInAs('manager');
  await manager.goto('/today');
  await manager.getByRole('combobox', {name: 'Whose list'}).click();
  await expect(manager.getByRole('option', {name: 'My list'})).toBeVisible();
  await manager.getByRole('option', {name: 'Jordan Reyes'}).click();
  await expect(manager).toHaveURL(new RegExp(`user_id=${sales2}`));
  await expect(manager.getByText("Jordan Reyes's list")).toBeVisible();
  await expect(rowOf(manager, 'TODAY-05 Test')).toBeVisible();
});
