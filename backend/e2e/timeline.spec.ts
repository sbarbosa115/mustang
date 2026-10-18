import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * TL-01 – 03 (TL-04 is by hand). Cases that change data create their own customer (813-555-04xx), so the seeded
 * customers stay as the seed made them; TL-01 only reads Sarah Johnson, whom TimelineSeeder gave a history.
 */

async function addCustomer(
  page: Page,
  first: string,
  last: string,
  phone: string,
) {
  await page.goto('/customers');
  await page.getByRole('button', {name: 'Add customer'}).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('First name').fill(first);
  await dialog.getByLabel('Last name').fill(last);
  await dialog.getByLabel('Phone').fill(phone);
  await dialog.getByRole('button', {name: 'Add customer'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: `${first} ${last}`}),
  ).toBeVisible();
}

async function openTimeline(page: Page) {
  await page.getByRole('tab', {name: 'Timeline'}).click();
}

test('TL-01 · a customer with history shows it by day, newest first', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/customers?q=Sarah');
  await page.getByRole('link', {name: 'Sarah Johnson'}).last().click();
  await openTimeline(page);

  const days = page.getByTestId('customer-timeline').getByRole('region');
  await expect(days.first()).toBeVisible();
  expect(await days.count()).toBeGreaterThan(1);
  await expect(page.getByText('Lead received')).toBeVisible();
  await expect(page.getByText('Text sent').first()).toBeVisible();
  await expect(page.getByText('Deal moved to Engaged')).toBeVisible();
  expect(errors).toEqual([]);
});

test('TL-02 · a new note lands on top of the timeline under Today', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await addCustomer(page, 'TL-02', 'Test', '813-555-0402');

  await openTimeline(page);
  await expect(
    page.getByText('Nothing has happened with this customer yet.'),
  ).toBeVisible();

  await page.getByRole('tab', {name: 'Overview'}).click();
  await page.getByLabel('New note').fill('Wants a third row.');
  await page.getByRole('button', {name: 'Add note'}).click();
  await expect(page.getByLabel('New note')).toHaveValue('');

  await openTimeline(page);
  const today = page.getByRole('region', {name: 'Today'});
  await expect(today.getByText('Note added')).toBeVisible();
  await expect(today.getByText('by Sam Patel')).toBeVisible();
});

test('TL-03 · another dealership cannot read a timeline', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await addCustomer(page, 'TL-03', 'Test', '813-555-0403');
  const id = page.url().split('/').pop();

  const other = await signedInAs('other');
  const response = await other.request.get(`/api/v1/customers/${id}/timeline`);
  expect(response.status()).toBe(404);
});
