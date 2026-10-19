import type {APIRequestContext, Page} from '@playwright/test';
import {emailTo} from './support/mail';
import {expect, test} from './support/test';

/**
 * The send pipeline through the API (the inbox screens are item "inbox-ui"). Each case creates its own customer,
 * named after the case, so the seed stays as it was.
 */

interface Created {
  id: string;
}

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
    data: {first_name: name, last_name: 'Test Customer', ...contact},
  });
  expect(response.status(), `creating ${name}`).toBe(201);
  return ((await response.json()) as Created).id;
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
    {
      headers,
      data: {
        channel,
        contact_value: contactValue,
        source: 'verbal',
        captured_where: 'showroom',
        wording: 'Customer agreed to be contacted.',
      },
    },
  );
  expect(response.status(), `recording ${channel} consent`).toBe(201);
}

async function conversationOf(page: Page, customerId: string): Promise<string> {
  const response = await page.request.get(
    `/api/v1/customers/${customerId}/conversation`,
  );
  expect(response.status()).toBe(200);
  return ((await response.json()) as {conversation: Created}).conversation.id;
}

async function signedIn(
  signedInAs: (account: 'sales' | 'bdc') => Promise<Page>,
  account: 'sales' | 'bdc',
): Promise<Page> {
  const page = await signedInAs(account);
  await page.goto('/today');
  return page;
}

test('MSG-01 · a simulated text reply lands in the customer conversation', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'bdc');
  const customerId = await customer(page, 'MSG-01', {phone: '+18135559101'});
  const {request, headers} = api(page);

  const response = await request.post('/api/v1/dev/inbound', {
    headers,
    data: {from: '(813) 555-9101', body: 'Is the Civic still there?'},
  });

  expect(response.status()).toBe(201);
  const result = (await response.json()) as {
    customer_id: string;
    conversation_id: string;
  };
  expect(result.customer_id).toBe(customerId);
  const thread = await (
    await request.get(`/api/v1/conversations/${result.conversation_id}`)
  ).json();
  expect(thread.conversation.unreplied).toBe(true);
  expect(thread.conversation.unread_count).toBe(1);
  expect(thread.messages.at(-1).body).toBe('Is the Civic still there?');

  // An unknown contact becomes a new customer (source other) with a lead; a fresh address per run, so it is unknown.
  const stranger = `msg-01-${Date.now()}@example.com`;
  const unknown = await request.post('/api/v1/dev/inbound', {
    headers,
    data: {from: stranger, channel: 'email', body: 'Hi'},
  });
  expect(unknown.status()).toBe(201);
  const created = (await unknown.json()) as {customer_id: string};
  expect(created.customer_id).not.toBe(customerId);
  const newcomer = await (
    await request.get(`/api/v1/customers/${created.customer_id}`)
  ).json();
  expect(newcomer.source).toBe('other');
});

test('MSG-02 · a send without consent is refused and kept', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'sales');
  const customerId = await customer(page, 'MSG-02', {phone: '+18135559102'});
  const conversation = await conversationOf(page, customerId);
  const {request, headers} = api(page);

  const response = await request.post(
    `/api/v1/conversations/${conversation}/messages`,
    {headers, data: {channel: 'text', body: 'Hello?'}},
  );

  expect(response.status()).toBe(409);
  const error = (await response.json()) as {
    error: string;
    detail: {message_id: string; consent: {state: string}};
  };
  expect(error.error).toBe('consent_missing');
  expect(error.detail.consent.state).toBe('missing');
  const provenance = await (
    await request.get(`/api/v1/messages/${error.detail.message_id}/provenance`)
  ).json();
  expect(provenance.status).toBe('blocked');
  expect(provenance.transport).toBeNull();
});

test('MSG-03 · a text to a consenting customer is sent with its provenance', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'sales');
  const customerId = await customer(page, 'MSG-03', {phone: '+18135559103'});
  await consent(page, customerId, 'text', '+18135559103');
  const conversation = await conversationOf(page, customerId);
  const {request, headers} = api(page);

  const response = await request.post(
    `/api/v1/conversations/${conversation}/messages`,
    {headers, data: {channel: 'text', body: 'Your car is ready.'}},
  );

  expect(response.status()).toBe(201);
  const message = (await response.json()) as {id: string; status: string};
  expect(message.status).toBe('sent');
  const provenance = await (
    await request.get(`/api/v1/messages/${message.id}/provenance`)
  ).json();
  expect(provenance.transport).toBe('fake_sms');
  expect(provenance.transport_message_id).toMatch(/^fake-/);
  expect(provenance.consent_state_snapshot.state).toBe('granted');
  expect(provenance.sent_by.name).not.toBe('');
});

test('MSG-04 · an email is queued, sent by the worker and lands in Mailpit', async ({
  signedInAs,
}) => {
  const page = await signedIn(signedInAs, 'sales');
  const address = 'msg-04@example.com';
  const customerId = await customer(page, 'MSG-04', {email: address});
  await consent(page, customerId, 'email', address);
  const conversation = await conversationOf(page, customerId);
  const {request, headers} = api(page);
  const since = new Date(Date.now() - 1000);

  const response = await request.post(
    `/api/v1/conversations/${conversation}/messages`,
    {
      headers,
      data: {channel: 'email', subject: 'MSG-04 your quote', body: 'Hello'},
    },
  );

  expect(response.status()).toBe(201);
  const message = (await response.json()) as {id: string; status: string};
  expect(message.status).toBe('queued');
  const email = await emailTo(request, address, {
    subject: /MSG-04 your quote/,
    since,
  });
  expect(email.text).toContain('Hello');
  await expect
    .poll(
      async () =>
        (
          await (
            await request.get(`/api/v1/messages/${message.id}/provenance`)
          ).json()
        ).status,
      {message: 'the worker marks the email sent'},
    )
    .toBe('sent');
});
