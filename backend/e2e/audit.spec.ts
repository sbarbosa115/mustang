import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * AUD-01 – 04. Seeded customers stay read-only: every case creates a customer (or a user) of its own through the API
 * and reads its history; the audit log itself is only read.
 */

// Admin screens are desktop first.
test.use({viewport: {width: 1280, height: 800}});

const run = Date.now().toString(36);

function uniquePhone(): string {
  // Outside the seeded ranges (+1813555 1001-1030) so a create is never a duplicate.
  return `+1813555${String(3000 + Math.floor(Math.random() * 6000))}`;
}

async function addCustomer(page: Page, label: string): Promise<string> {
  const response = await page.request.post('/api/v1/customers', {
    data: {first_name: label, last_name: `Audit${run}`, phone: uniquePhone()},
    headers: {Origin: new URL(page.url()).origin},
  });
  expect(response.status(), 'adding a customer through the API').toBe(201);
  return ((await response.json()) as {id: string}).id;
}

async function openHistory(page: Page, type: string, id: string) {
  await page.goto(`/settings/audit?entity_type=${type}&entity_id=${id}`);
  await expect(page.getByRole('heading', {name: 'Audit log'})).toBeVisible();
}

test('AUD-01 · an admin sees a change with its old and new value and who made it', async ({
  signedInAs,
}) => {
  const rep = await signedInAs('sales');
  await rep.goto('/today');
  const id = await addCustomer(rep, 'AUD01');
  const patched = await rep.request.patch(`/api/v1/customers/${id}`, {
    data: {last_name: `Renamed${run}`},
    headers: {Origin: new URL(rep.url()).origin},
  });
  expect(patched.status()).toBe(200);

  const admin = await signedInAs('admin');
  const errors = consoleErrors(admin);
  await openHistory(admin, 'Customer', id);

  const table = admin.getByRole('table');
  const changed = table.getByRole('row').filter({hasText: 'Changed'});
  await expect(changed).toHaveCount(1);
  await expect(changed).toContainText('Sam Patel');
  await expect(changed).toContainText('last_name');
  await expect(changed).toContainText(`Audit${run}`);
  await expect(changed).toContainText(`Renamed${run}`);
  await expect(table.getByRole('row').filter({hasText: 'Created'})).toHaveCount(
    1,
  );
  await expect(admin.getByText(/History of Customer/)).toBeVisible();

  await admin.getByRole('button', {name: 'Clear filters'}).click();
  await expect(admin).toHaveURL(/\/settings\/audit$/);
  expect(errors).toEqual([]);
});

test('AUD-02 · opening a customer record is recorded as a view by that person', async ({
  signedInAs,
}) => {
  const rep = await signedInAs('bdc');
  await rep.goto('/today');
  const id = await addCustomer(rep, 'AUD02');
  await rep.goto(`/customers/${id}`);
  await expect(
    rep.getByRole('heading', {level: 1, name: `AUD02 Audit${run}`}),
  ).toBeVisible();

  const admin = await signedInAs('admin');
  await openHistory(admin, 'Customer', id);
  const viewed = admin
    .getByRole('table')
    .getByRole('row')
    .filter({hasText: 'Viewed'});
  await expect(viewed.first()).toContainText('Brianna Diaz');
  await expect(
    viewed.first().getByRole('link', {name: 'Open the customer'}),
  ).toHaveAttribute('href', `/customers/${id}`);
});

test('AUD-03 · a new password is recorded as changed but never shown', async ({
  signedInAs,
}) => {
  const admin = await signedInAs('admin');
  await admin.goto('/settings/audit');
  const created = await admin.request.post('/api/v1/users', {
    data: {
      email: `aud03.${run}@mustang.test`,
      first_name: 'AUD03',
      last_name: `Audit${run}`,
      role: 'sales',
      password: 'smoke-test-password-1',
    },
    headers: {Origin: new URL(admin.url()).origin},
  });
  expect(created.status()).toBe(201);
  const userId = ((await created.json()) as {id: string}).id;

  const api = await admin.request.get(
    `/api/v1/audit?entity_type=User&entity_id=${userId}`,
  );
  const body = (await api.json()) as {
    items: Array<{action: string; changes: Record<string, {new: unknown}>}>;
  };
  expect(body.items[0]?.action).toBe('create');
  expect(
    body.items[0]?.changes.password_hash?.new,
    'the hash never leaves the server',
  ).toBe('[masked]');

  await openHistory(admin, 'User', userId);
  const row = admin
    .getByRole('table')
    .getByRole('row')
    .filter({hasText: 'Created'});
  await expect(row).toContainText('password_hash');
  await expect(row).toContainText('hidden');
  await expect(row).not.toContainText('[masked]');
  await expect(row).not.toContainText('$');
});

test('AUD-04 · only admins read the log, and another dealership sees none of it', async ({
  signedInAs,
}) => {
  const manager = await signedInAs('manager');
  await manager.goto('/settings');
  await expect(
    manager.getByRole('link', {name: 'Audit log', exact: true}),
  ).toHaveCount(0);
  expect(
    (await manager.request.get('/api/v1/audit')).status(),
    'a manager is not an admin',
  ).toBe(403);
  const id = await addCustomer(manager, 'AUD04');

  const admin = await signedInAs('admin');
  await admin.goto('/settings');
  await expect(
    admin.getByRole('link', {name: 'Audit log', exact: true}),
  ).toBeVisible();

  const other = await signedInAs('other');
  const theirs = await other.request.get(`/api/v1/audit?entity_id=${id}`);
  expect(theirs.status()).toBe(200);
  expect(
    ((await theirs.json()) as {total: number}).total,
    'another rooftop sees nothing of this one',
  ).toBe(0);
});
