import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * DEAL-01 – 07. A case that changes data creates its own customer (and deal), named after the case, so the seeded
 * deals stay as the seed made them.
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

/** A customer with an open deal in New; returns the deal id. */
async function dealFor(page: Page, name: string, phone: string) {
  const customerId = await customer(page, name, phone);
  const response = await page.request.post('/api/v1/deals', {
    headers: origin(page),
    data: {customer_id: customerId},
  });
  expect(response.status(), `opening a deal for ${name}`).toBe(201);
  return ((await response.json()) as {id: string}).id;
}

test('DEAL-01 · a rep sees the open deals by stage', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/deals');

  await expect(
    page.getByRole('heading', {level: 1, name: 'Deals'}),
  ).toBeVisible();
  const sold = page.getByRole('region', {name: /^Sold, /});
  await expect(sold.getByText('Chris Miller')).toBeVisible();
  await expect(
    page.getByRole('region', {name: /^Proposal, /}).getByText('Ashley Garcia'),
  ).toBeVisible();
  await expect(page.getByRole('region', {name: /^Lost/})).toHaveCount(0);

  await page.getByText('Lost', {exact: true}).click();
  await expect(page.getByText('Lost: Bought elsewhere').first()).toBeVisible();
  await expect(page.getByText('James Rodriguez')).toBeVisible();

  await page.getByText('Open deals', {exact: true}).click();
  await page.getByLabel('Search by customer or vehicle').fill('zzz');
  await expect(page.getByText('No deals match these filters.')).toBeVisible();
  await page.getByRole('button', {name: 'Show all'}).click();
  await expect(sold.getByText('Chris Miller')).toBeVisible();
  expect(errors).toEqual([]);
});

test('DEAL-02 · open a deal for a customer', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  await page.goto('/deals');
  await customer(page, 'DEAL-02', '(813) 555-9602');

  await page.getByRole('button', {name: 'New deal'}).click();
  let dialog = page.getByRole('dialog', {name: 'New deal'});
  await dialog.getByLabel('Search customers').fill('DEAL-02');
  await dialog.getByRole('button', {name: 'Choose DEAL-02 Test'}).click();
  await dialog
    .getByLabel('Vehicle of interest (optional)')
    .fill('Blue Mustang');
  await dialog.getByRole('button', {name: 'Open deal'}).click();

  await expect(page.getByText('Deal opened for DEAL-02 Test.')).toBeVisible();
  await expect(
    page.getByRole('heading', {level: 1, name: 'DEAL-02 Test'}),
  ).toBeVisible();
  await expect(page.getByText('Blue Mustang')).toBeVisible();
  await expect(page.getByText('Sam Patel')).toBeVisible();
  const url = page.url();

  await page.goto('/deals');
  await page.getByRole('button', {name: 'New deal'}).click();
  dialog = page.getByRole('dialog', {name: 'New deal'});
  await dialog.getByLabel('Search customers').fill('DEAL-02');
  await dialog.getByRole('button', {name: 'Choose DEAL-02 Test'}).click();
  await dialog.getByRole('button', {name: 'Open deal'}).click();
  await expect(
    dialog.getByText('This customer already has an open deal.'),
  ).toBeVisible();
  await dialog.getByRole('link', {name: 'Open the existing deal'}).click();
  await expect(page).toHaveURL(url);
});

test('DEAL-03 · move a deal forward, to lost, and reopen it', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/deals');
  const id = await dealFor(page, 'DEAL-03', '(813) 555-9603');
  await page.goto(`/deals/${id}`);

  await page.getByRole('button', {name: 'Move stage'}).click();
  let dialog = page.getByRole('dialog');
  await dialog.getByText('Proposal', {exact: true}).click();
  await dialog.getByRole('button', {name: 'Move', exact: true}).click();
  await expect(page.getByText('Moved to Proposal.')).toBeVisible();

  await page.getByRole('button', {name: 'Move stage'}).click();
  dialog = page.getByRole('dialog');
  await dialog.getByText('Lost', {exact: true}).click();
  await dialog.getByRole('button', {name: 'Move', exact: true}).click();
  await expect(dialog.getByText('Say why the deal was lost.')).toBeVisible();
  await dialog.getByLabel('Why was it lost?').fill('Price');
  await dialog.getByRole('button', {name: 'Move', exact: true}).click();
  await expect(page.getByText('Moved to Lost.')).toBeVisible();
  await expect(page.getByText('Why it was lost')).toBeVisible();

  await page.getByRole('button', {name: 'Reopen'}).click();
  await page.getByRole('button', {name: 'Reopen to engaged'}).click();
  await expect(page.getByText('Moved to Engaged.')).toBeVisible();
  await expect(page.getByText('Why it was lost')).toHaveCount(0);
  await expect(page.getByRole('button', {name: 'Move stage'})).toBeVisible();
});

test('DEAL-04 · set the vehicle of interest and the trade-in', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/deals');
  const id = await dealFor(page, 'DEAL-04', '(813) 555-9604');
  await page.goto(`/deals/${id}`);

  await page.getByRole('button', {name: 'Change vehicle'}).click();
  let dialog = page.getByRole('dialog', {name: 'Vehicle of interest'});
  await dialog.getByLabel('Search inventory').fill('MM-1005');
  await dialog
    .getByRole('button', {name: 'Choose 2026 Toyota RAV4, stock MM-1005'})
    .click();
  await dialog.getByRole('button', {name: 'Save'}).click();
  await expect(page.getByText('Vehicle of interest saved.')).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Open 2026 Toyota RAV4 in inventory'}),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Change trade-in'}).click();
  dialog = page.getByRole('dialog', {name: 'Trade-in'});
  await dialog.getByLabel('Year').fill('1800');
  await dialog.getByRole('button', {name: 'Save'}).click();
  await expect(
    dialog.getByText('Enter a year between 1950 and 2100.'),
  ).toBeVisible();
  await dialog.getByLabel('Year').fill('2017');
  await dialog.getByLabel('Make').fill('Honda');
  await dialog.getByLabel('Model').fill('Civic');
  await dialog.getByLabel('Mileage').fill('82000');
  await dialog.getByLabel('Payoff (dollars)').fill('4500');
  await dialog.getByRole('button', {name: 'Save'}).click();
  await expect(page.getByText('2017 Honda Civic')).toBeVisible();
  await expect(page.getByText('82,000 mi')).toBeVisible();

  await page.getByRole('button', {name: 'Change trade-in'}).click();
  await page.getByRole('button', {name: 'Remove trade-in'}).click();
  await expect(page.getByText('No trade-in')).toBeVisible();
});

test('DEAL-05 · a manager takes a deal', async ({signedInAs}) => {
  const sales = await signedInAs('sales');
  await sales.goto('/deals');
  const id = await dealFor(sales, 'DEAL-05', '(813) 555-9605');

  const page = await signedInAs('manager');
  await page.goto(`/deals/${id}`);
  await expect(page.getByText('Sam Patel')).toBeVisible();
  await page.getByRole('button', {name: 'Change', exact: true}).click();
  await page.getByRole('button', {name: 'Assign to me'}).click();

  await expect(page.getByText('Assigned to Maria Gonzalez.')).toBeVisible();
  await page.goto('/deals?scope=mine&q=DEAL-05');
  await expect(page.getByText('DEAL-05 Test')).toBeVisible();
});

test('DEAL-06 · a reply from the customer engages a deal in New', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/deals');
  const id = await dealFor(page, 'DEAL-06', '(813) 555-9606');

  const reply = await page.request.post('/api/v1/dev/inbound', {
    headers: origin(page),
    data: {from: '(813) 555-9606', body: 'Still available?'},
  });
  expect(reply.status()).toBe(201);

  const deal = await page.request.get(`/api/v1/deals/${id}`);
  expect(((await deal.json()) as {stage: string}).stage).toBe('engaged');
  await page.goto(`/deals/${id}`);
  await expect(page.getByRole('button', {name: 'Move stage'})).toBeVisible();
});

test("DEAL-07 · another dealership's deal does not exist", async ({
  signedInAs,
}) => {
  const sales = await signedInAs('sales');
  await sales.goto('/deals');
  const list = await sales.request.get('/api/v1/deals?per_page=1');
  const ours = ((await list.json()) as {items: Array<{id: string}>}).items[0];
  expect(ours).toBeDefined();

  const page = await signedInAs('other');
  await page.goto(`/deals/${ours?.id}`);
  await expect(
    page.getByText('This deal does not exist or is not in your dealership.'),
  ).toBeVisible();
  await page.goto('/deals');
  await expect(page.getByText('Other1 Customer')).toBeVisible();
  await expect(page.getByText('Chris Miller')).toHaveCount(0);
});
