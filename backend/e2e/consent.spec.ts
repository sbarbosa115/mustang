import type {APIRequestContext, Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * CONS-01 – 06. Each case creates its own customer, named after the case, with a phone of its own, so the seeded
 * customers and their ledger stay as the seed made them.
 */

function api(page: Page): {
  request: APIRequestContext;
  headers: Record<string, string>;
} {
  // State-changing requests must come from the app's own origin (the page is open on it).
  return {request: page.request, headers: {Origin: new URL(page.url()).origin}};
}

async function customer(
  page: Page,
  name: string,
  contact: {phone?: string; email?: string},
): Promise<string> {
  const {request, headers} = api(page);
  const response = await request.post('/api/v1/customers', {
    headers,
    data: {first_name: name, last_name: 'Test', ...contact},
  });
  expect(response.status(), `creating ${name}`).toBe(201);
  return ((await response.json()) as {id: string}).id;
}

async function grant(page: Page, customerId: string, phone: string) {
  const {request, headers} = api(page);
  const response = await request.post(
    `/api/v1/customers/${customerId}/consents`,
    {headers, data: {channel: 'text', contact_value: phone, source: 'verbal'}},
  );
  expect(response.status()).toBe(201);
}

async function reply(page: Page, from: string, body: string) {
  const {request, headers} = api(page);
  const response = await request.post('/api/v1/dev/inbound', {
    headers,
    data: {from, body},
  });
  expect(response.status(), `the customer texts ${body}`).toBe(201);
}

async function signedIn(
  signedInAs: (account: 'sales' | 'bdc' | 'manager') => Promise<Page>,
  account: 'sales' | 'bdc' | 'manager',
): Promise<Page> {
  const page = await signedInAs(account);
  await page.goto('/today');
  return page;
}

test('CONS-01 · record text consent from the consent page', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'sales');
  const errors = consoleErrors(page);
  const id = await customer(page, 'CONS-01', {phone: '+18135559301'});

  await page.goto(`/customers/${id}`);
  await page.getByRole('button', {name: 'Customer actions'}).click();
  await page.getByRole('menuitem', {name: 'Consent'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: 'Consent for CONS-01 Test'}),
  ).toBeVisible();
  await expect(page.getByText('Text: Missing').first()).toBeVisible();
  await expect(page.getByText('No consent recorded yet.')).toBeVisible();

  await page.getByRole('button', {name: 'Record consent'}).first().click();
  const dialog = page.getByRole('dialog', {name: 'Record consent'});
  await expect(
    dialog.getByRole('combobox', {name: 'Phone number or email'}),
  ).toHaveText('(813) 555-9301');
  await dialog.getByLabel('Where').fill('Showroom desk');
  await dialog
    .getByLabel('What they agreed to')
    .fill('Texts about my vehicle search.');
  await dialog.getByRole('button', {name: 'Record consent'}).click();

  await expect(
    page.getByText('Text consent recorded for (813) 555-9301.'),
  ).toBeVisible();
  await expect(page.getByText('Text: Granted').first()).toBeVisible();
  await expect(page.getByText('by Sam Patel').first()).toBeVisible();
  await expect(page.getByText('Showroom desk').first()).toBeVisible();
  expect(errors).toEqual([]);
});

test('CONS-02 · revoking asks first, names the contact and blocks the send', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'sales');
  const id = await customer(page, 'CONS-02', {phone: '+18135559302'});
  await grant(page, id, '+18135559302');

  await page.goto(`/customers/${id}/consent`);
  await page
    .getByRole('button', {name: 'Revoke Text consent for (813) 555-9302'})
    .first()
    .click();
  const dialog = page.getByRole('alertdialog', {
    name: 'Revoke Text consent for (813) 555-9302?',
  });
  await dialog.getByLabel('Reason').fill('Asked us not to text');
  await dialog.getByRole('button', {name: 'Revoke consent'}).click();

  await expect(page.getByText('Text: Revoked').first()).toBeVisible();
  await expect(page.getByText('Asked us not to text').first()).toBeVisible();

  const {request, headers} = api(page);
  const conversation = (
    await (await request.get(`/api/v1/customers/${id}/conversation`)).json()
  ).conversation.id as string;
  const send = await request.post(
    `/api/v1/conversations/${conversation}/messages`,
    {headers, data: {channel: 'text', body: 'Still there?'}},
  );
  expect(send.status()).toBe(409);
  expect((await send.json()).error).toBe('opted_out');
});

test('CONS-03 · a STOP reply opts the phone out and the record says why', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'bdc');
  const id = await customer(page, 'CONS-03', {phone: '+18135559303'});
  await grant(page, id, '+18135559303');

  await reply(page, '(813) 555-9303', 'STOP');

  await page.goto(`/customers/${id}/consent`);
  await expect(page.getByText('Text: Opted out').first()).toBeVisible();
  await expect(
    page.getByText(/Text is blocked: this contact replied STOP/),
  ).toBeVisible();
  await expect(page.getByText('Replied STOP').first()).toBeVisible();
  await expect(
    page.getByRole('button', {name: /^Revoke Text consent/}),
  ).toHaveCount(0);
});

test('CONS-04 · START lifts the opt-out and records consent from the keyword', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'sales');
  const id = await customer(page, 'CONS-04', {phone: '+18135559304'});
  await reply(page, '+18135559304', 'stop');
  await reply(page, '+18135559304', 'Start');

  await page.goto(`/customers/${id}/consent`);
  await expect(page.getByText('Text: Granted').first()).toBeVisible();
  await expect(page.getByText('Text keyword').first()).toBeVisible();
  await expect(page.getByText('by the customer').first()).toBeVisible();
});

test.describe('desktop', () => {
  test.use({viewport: {width: 1280, height: 800}});

  test('CONS-05 · the opt-outs screen lists and searches opted-out contacts', async ({
    signedInAs,
  }) => {
    const page = await signedIn(signedInAs, 'manager');
    const errors = consoleErrors(page);
    await customer(page, 'CONS-05', {phone: '+18135559305'});
    await reply(page, '+18135559305', 'UNSUBSCRIBE');

    await page.goto('/settings');
    await page.getByRole('link', {name: 'Opt-outs'}).click();
    await expect(page).toHaveURL(/\/settings\/suppressions$/);
    await expect(page.getByRole('heading', {name: 'Opt-outs'})).toBeVisible();

    await page.getByLabel('Search opt-outs').fill('813-555-9305');
    await expect(
      page.getByRole('cell', {name: '(813) 555-9305'}),
    ).toBeVisible();
    await expect(page.getByRole('cell', {name: 'Replied STOP'})).toBeVisible();

    await page.getByLabel('Search opt-outs').fill('zzz-nobody');
    await expect(
      page.getByText('Nothing matches these filters.'),
    ).toBeVisible();
    await page.getByRole('button', {name: 'Show all'}).click();
    await expect(page.getByLabel('Search opt-outs')).toHaveValue('');
    expect(errors).toEqual([]);
  });
});

test("CONS-06 · another dealership cannot see or change a customer's consent", async ({
  signedInAs,
}) => {
  const sales = await signedIn(signedInAs, 'sales');
  const id = await customer(sales, 'CONS-06', {phone: '+18135559306'});

  const other = await signedInAs('other');
  await other.goto(`/customers/${id}/consent`);
  await expect(
    other.getByText(
      'This customer does not exist or is not in your dealership.',
    ),
  ).toBeVisible();
  const {request, headers} = api(other);
  const list = await request.get(`/api/v1/customers/${id}/consents`);
  expect(list.status()).toBe(404);
  const record = await request.post(`/api/v1/customers/${id}/consents`, {
    headers,
    data: {channel: 'text', contact_value: '+18135559306', source: 'verbal'},
  });
  expect(record.status()).toBe(404);
});
