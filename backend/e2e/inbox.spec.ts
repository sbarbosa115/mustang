import type {APIRequestContext, Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * INB-01 – 08, on the phone project (390 × 844). Each case creates its own customer, named after the case, with a
 * phone of its own (555-94xx), so the seeded customers and their conversations stay as the seed made them.
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

async function consent(
  page: Page,
  customerId: string,
  channel: 'text' | 'email',
  contactValue: string,
): Promise<void> {
  const {request, headers} = api(page);
  const response = await request.post(
    `/api/v1/customers/${customerId}/consents`,
    {headers, data: {channel, contact_value: contactValue, source: 'verbal'}},
  );
  expect(response.status(), `recording ${channel} consent`).toBe(201);
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

async function conversationOf(page: Page, customerId: string): Promise<string> {
  const response = await page.request.get(
    `/api/v1/customers/${customerId}/conversation`,
  );
  expect(response.status()).toBe(200);
  return ((await response.json()) as {conversation: {id: string}}).conversation
    .id;
}

async function signedIn(
  signedInAs: (account: 'sales') => Promise<Page>,
): Promise<Page> {
  const page = await signedInAs('sales');
  await page.goto('/today');
  return page;
}

function folder(page: Page, name: string) {
  return page
    .getByRole('group', {name: 'Folders'})
    .getByRole('button', {name: new RegExp(`^${name}`)});
}

test('INB-01 · folders with counts, and Unreplied narrows the list', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const errors = consoleErrors(page);
  await customer(page, 'INB-01', {phone: '+18135559401'});
  await reply(page, '(813) 555-9401', 'Is the Civic still there?');

  await page.goto('/inbox');
  await expect(folder(page, 'All')).toHaveAttribute('aria-pressed', 'true');
  await expect(folder(page, 'Unreplied')).toContainText(/\d+/);
  await expect(folder(page, 'Fumbled')).toContainText(/\d+/);

  await folder(page, 'Unreplied').click();
  await expect(folder(page, 'Unreplied')).toHaveAttribute(
    'aria-pressed',
    'true',
  );
  await page.getByLabel('Search conversations').fill('INB-01');
  const row = page.getByRole('link', {name: /INB-01 Test/});
  await expect(row).toContainText('Is the Civic still there?');
  await expect(row).toContainText('Unreplied');
  await expect(row).toContainText('1 unread');
  expect(errors).toEqual([]);
});

test('INB-02 · a search that matches nothing offers Show all', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  await page.goto('/inbox?folder=unreplied');

  await page.getByLabel('Search conversations').fill('zzzz-nothing');
  await expect(page.getByText('Nothing matches these filters.')).toBeVisible();
  await expect(page).toHaveURL(/q=zzzz-nothing/);

  await page.getByRole('button', {name: 'Show all'}).click();
  await expect(page.getByLabel('Search conversations')).toHaveValue('');
  await expect(folder(page, 'All')).toHaveAttribute('aria-pressed', 'true');

  await page.getByRole('combobox', {name: 'Assigned rep'}).click();
  await expect(page.getByRole('option', {name: 'Everyone'})).toBeVisible();
  await expect(
    page.getByRole('option', {name: /^Jordan Reyes · \d+ unreplied$/}),
  ).toBeVisible();
  await expect(page.getByRole('option', {name: /Alex Admin/})).toHaveCount(0);
});

test('INB-03 · on a phone a conversation opens full screen and is marked read', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const errors = consoleErrors(page);
  await customer(page, 'INB-03', {phone: '+18135559403'});
  await reply(page, '(813) 555-9403', 'Can I come at 5?');

  await page.goto('/inbox?folder=unreplied');
  await page.getByLabel('Search conversations').fill('INB-03');
  await page.getByRole('link', {name: /INB-03 Test/}).click();

  await expect(page).toHaveURL(/\/inbox\/[0-9a-f-]+\?/);
  await expect(page.getByRole('heading', {name: 'INB-03 Test'})).toBeVisible();
  const today = page.getByRole('listitem', {name: 'Today'});
  await expect(today).toContainText('Can I come at 5?');
  await expect(
    page.locator('[data-direction="in"]', {hasText: 'Can I come at 5?'}),
  ).toBeVisible();

  await page.getByRole('link', {name: 'Back to inbox'}).click();
  await expect(page).toHaveURL(/\/inbox\?.*folder=unreplied/);
  const row = page.getByRole('link', {name: /INB-03 Test/});
  await expect(row, 'opening it marked it read').not.toContainText('unread');
  expect(errors).toEqual([]);
});

test('INB-04 · without consent the composer says why and Send stays disabled', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const customerId = await customer(page, 'INB-04', {phone: '+18135559404'});
  const conversation = await conversationOf(page, customerId);

  await page.goto(`/inbox/${conversation}`);
  const composer = page.getByRole('form', {name: 'Reply'});
  await expect(composer.getByText('Text: Missing')).toBeVisible();
  await expect(
    composer.getByText('No consent recorded for Text'),
  ).toBeVisible();
  await expect(composer.getByText('No email address on file')).toBeVisible();
  await expect(composer.getByRole('button', {name: 'Text'})).toBeDisabled();
  await expect(composer.getByRole('button', {name: 'Send'})).toBeDisabled();

  await composer.getByRole('link', {name: 'Record consent'}).click();
  await expect(page).toHaveURL(`/customers/${customerId}/consent`);
});

test('INB-05 · a text to a consenting customer is sent and leaves Unreplied', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const errors = consoleErrors(page);
  const customerId = await customer(page, 'INB-05', {phone: '+18135559405'});
  await consent(page, customerId, 'text', '+18135559405');
  const conversation = await reply(page, '(813) 555-9405', 'Is it ready?');

  await page.goto(`/inbox/${conversation}?folder=unreplied`);
  const composer = page.getByRole('form', {name: 'Reply'});
  await composer.getByLabel('Message').fill('Your car is ready.');
  await composer.getByRole('button', {name: 'Send'}).click();

  await expect(composer.getByLabel('Message')).toHaveValue('');
  const sent = page.locator('[data-direction="out"]', {
    hasText: 'Your car is ready.',
  });
  await expect(sent).toContainText('Text');
  await expect(sent).toContainText('Sam Patel');

  await page.getByRole('link', {name: 'Back to inbox'}).click();
  await expect(folder(page, 'Unreplied')).toHaveAttribute(
    'aria-pressed',
    'true',
  );
  await page.getByLabel('Search conversations').fill('INB-05');
  await expect(
    page.getByText('Nothing matches these filters.'),
    'answered, it left the Unreplied folder',
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test('INB-06 · a template fills its placeholders and an email needs a subject', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const address = 'inb-06@example.com';
  const customerId = await customer(page, 'INB-06', {
    phone: '+18135559406',
    email: address,
  });
  await consent(page, customerId, 'text', '+18135559406');
  await consent(page, customerId, 'email', address);
  const conversation = await conversationOf(page, customerId);

  await page.goto(`/inbox/${conversation}`);
  const composer = page.getByRole('form', {name: 'Reply'});
  await composer.getByRole('button', {name: 'Templates'}).click();
  await page.getByRole('button', {name: /First reply/}).click();
  await expect(composer.getByLabel('Message')).toHaveValue(
    'Hi INB-06, this is Sam Patel at Mustang Motors Tampa. Thanks for reaching out. When is a good time to talk?',
  );

  await composer.getByRole('button', {name: 'Email'}).click();
  await composer.getByLabel('Message').fill('Your quote is attached below.');
  await expect(composer.getByText('An email needs a subject.')).toBeVisible();
  await expect(composer.getByRole('button', {name: 'Send'})).toBeDisabled();
  await composer.getByLabel('Subject').fill('INB-06 your quote');
  await composer.getByRole('button', {name: 'Send'}).click();

  await expect(
    page.locator('[data-direction="out"]', {hasText: 'INB-06 your quote'}),
  ).toContainText('Email');
});

test('INB-07 · a vehicle from inventory is added to the message and sent', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const customerId = await customer(page, 'INB-07', {phone: '+18135559407'});
  await consent(page, customerId, 'text', '+18135559407');
  const conversation = await conversationOf(page, customerId);

  await page.goto(`/inbox/${conversation}`);
  const composer = page.getByRole('form', {name: 'Reply'});
  await composer.getByRole('button', {name: 'Vehicle'}).click();
  const dialog = page.getByRole('dialog', {name: 'Send a vehicle'});
  await dialog.getByLabel('Search inventory').fill('MM-1001');
  await dialog.getByRole('button', {name: /MM-1001/}).click();
  await expect(dialog.getByText('MM-1001').last()).toBeVisible();
  await dialog.getByRole('button', {name: 'Add to message'}).click();

  await expect(composer.getByLabel('Message')).toHaveValue(/Stock #MM-1001/);
  await composer.getByRole('button', {name: 'Send'}).click();
  await expect(
    page.locator('[data-direction="out"]', {hasText: 'Stock #MM-1001'}),
  ).toBeVisible();
});

test('INB-08 · a simulated STOP reply blocks text in the composer', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs);
  const customerId = await customer(page, 'INB-08', {phone: '+18135559408'});
  await consent(page, customerId, 'text', '+18135559408');
  const conversation = await conversationOf(page, customerId);

  await page.goto(`/inbox/${conversation}`);
  const composer = page.getByRole('form', {name: 'Reply'});
  await expect(composer.getByRole('button', {name: 'Text'})).toBeEnabled();

  await page.getByRole('button', {name: 'Simulate a reply'}).click();
  const dialog = page.getByRole('dialog', {name: 'Simulate a customer reply'});
  await expect(dialog.getByLabel('From')).toHaveValue('+18135559408');
  await dialog.getByLabel('Message').fill('STOP');
  await dialog.getByRole('button', {name: 'Add reply'}).click();

  await expect(
    page.locator('[data-direction="in"]', {hasText: 'STOP'}),
  ).toBeVisible();
  await expect(composer.getByText('Text: Opted out')).toBeVisible();
  await expect(
    composer.getByText('This contact opted out of Text'),
  ).toBeVisible();
  await expect(composer.getByRole('button', {name: 'Send'})).toBeDisabled();
});
