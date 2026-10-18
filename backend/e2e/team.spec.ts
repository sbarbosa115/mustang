import type {APIRequestContext, Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * MGR-01 – 04 at desktop width (the team page is desktop first). Each case adds its own customer (555-981x).
 */
test.use({viewport: {width: 1280, height: 800}});

function api(page: Page): {
  request: APIRequestContext;
  headers: Record<string, string>;
} {
  return {request: page.request, headers: {Origin: new URL(page.url()).origin}};
}

async function userIdOf(page: Page, email: string): Promise<string> {
  const response = await page.request.get('/api/v1/users?per_page=100');
  const users = (await response.json()) as {
    items: Array<{id: string; email: string}>;
  };
  return users.items.find((u) => u.email === email)?.id ?? '';
}

function rowOf(page: Page, name: string) {
  return page.getByRole('article').filter({hasText: name});
}

test('MGR-01 · the team page sums up the deals at risk', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  const errors = consoleErrors(page);
  await page.goto('/team');

  await expect(
    page.getByRole('heading', {level: 1, name: 'Team'}),
  ).toBeVisible();
  const summary = page.getByRole('region', {name: 'Summary'});
  await expect(summary.getByText('Deals that need you')).toBeVisible();
  await expect(summary.getByText('Gross at risk')).toBeVisible();
  await expect(summary.getByText(/\$[\d,]+/)).toBeVisible();
  await expect(
    summary.getByText('Reps with unanswered customers'),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('MGR-02 · a lead uncontacted for two hours is in the queue and "Assign to me" takes it', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await page.goto('/team');
  const {request, headers} = api(page);
  const lead = await request.post('/api/v1/leads', {
    headers,
    data: {
      first_name: 'MGR-02',
      last_name: 'Test',
      phone: '+18135559812',
      source: 'phone',
      assigned_user_id: await userIdOf(page, 'sales@mustang.test'),
      received_at: new Date(Date.now() - 3 * 3600_000).toISOString(),
    },
  });
  expect(lead.status()).toBe(201);

  await page.goto('/team');
  const row = rowOf(page, 'MGR-02 Test');
  await expect(row).toContainText('Lead waiting 3 hours, not contacted yet');
  await expect(row).toContainText('Assigned to Sam Patel');
  await expect(row).toContainText('$3,000 at risk');

  await row.getByRole('button', {name: 'Assign to me'}).click();
  await expect(
    page.getByText("MGR-02 Test's deal is assigned to you."),
  ).toBeVisible();
  await expect(row).toContainText('Assigned to Maria Gonzalez');
  await expect(row.getByRole('button', {name: 'Assign to me'})).toHaveCount(0);
});

test("MGR-03 · a rep's unanswered customers are counted and open their list", async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await page.goto('/team');
  const {request, headers} = api(page);
  const sales2 = await userIdOf(page, 'sales2@mustang.test');
  const created = await request.post('/api/v1/customers', {
    headers,
    data: {first_name: 'MGR-03', last_name: 'Test', phone: '+18135559813'},
  });
  expect(created.status()).toBe(201);
  const id = ((await created.json()) as {id: string}).id;
  const assigned = await request.patch(`/api/v1/customers/${id}/assignments`, {
    headers,
    data: {sales1_user_id: sales2},
  });
  expect(assigned.status()).toBe(200);
  const inbound = await request.post('/api/v1/dev/inbound', {
    headers,
    data: {from: '(813) 555-9813', body: 'Hello'},
  });
  expect(inbound.status()).toBe(201);

  await page.goto('/team');
  const summary = page.getByRole('region', {name: 'Summary'});
  const rep = summary.getByRole('listitem').filter({hasText: 'Jordan Reyes'});
  await expect(rep).toContainText(/\d+ waiting/);
  await rep.getByRole('link', {name: "Open Jordan Reyes's list"}).click();

  await expect(page).toHaveURL(new RegExp(`/today\\?user_id=${sales2}`));
  await expect(page.getByText("Jordan Reyes's list")).toBeVisible();
  await expect(rowOf(page, 'MGR-03 Test')).toBeVisible();
});

test('MGR-04 · reps are kept out of the team queue', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  await page.goto('/team');

  await expect(page).toHaveURL(/\/today$/);
  const refused = await page.request.get('/api/v1/worklist/interventions');
  expect(refused.status()).toBe(403);
});
