import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

const CUSTOMERS_HEADER =
  'first_name,last_name,phone,email,address,sales_rep_email,sms_consent,email_consent,consent_source,notes\n';
const LEADS_HEADER =
  'first_name,last_name,phone,email,source,vehicle_interest,received_at,sales_rep_email,notes\n';

/** A phone number no earlier run has used (941 area code; the seed uses 813 and 727). */
function freshPhone(): string {
  const local = String(2_000_000 + Math.floor(Math.random() * 7_999_999));
  return `941${local}`;
}

async function uploadFile(
  page: Page,
  type: 'Customers' | 'Open leads',
  name: string,
  csv: string,
): Promise<void> {
  await page.getByRole('radio', {name: type}).click();
  await page.getByLabel('CSV file').setInputFiles({
    name,
    mimeType: 'text/csv',
    buffer: Buffer.from(csv),
  });
  await page.getByRole('button', {name: 'Upload and import'}).click();
  await expect(page.getByText(`${name} is queued`)).toBeVisible();
}

/** The newest import's card (phone layout), once the worker has finished it. */
function newest(page: Page) {
  return page
    .getByRole('region', {name: 'Recent imports'})
    .getByRole('listitem')
    .first();
}

test('IMP-01 · a manager imports a customers file and the customers are there', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  const errors = consoleErrors(page);
  await page.goto('/settings/import');
  await expect(
    page.getByRole('heading', {name: 'Import customers and leads'}),
  ).toBeVisible();
  await expect(
    page.getByRole('link', {name: 'Download the sample customers.csv'}),
  ).toHaveAttribute('href', '/api/v1/imports/template/customers.csv');
  const sample = await page.request.get(
    '/api/v1/imports/template/customers.csv',
  );
  expect(sample.status()).toBe(200);
  expect(await sample.text()).toContain('sms_consent');

  const phone = freshPhone();
  await uploadFile(
    page,
    'Customers',
    'imp01.csv',
    `${CUSTOMERS_HEADER}Imp,Zero One,${phone},,,sales@mustang.test,yes,,Showroom,From IMP-01\n`,
  );
  await expect(newest(page)).toContainText('Done', {timeout: 30_000});
  await expect(newest(page)).toContainText(
    '1 created, 0 updated, 0 unchanged, 0 failed',
  );

  await page.goto('/customers');
  await page
    .getByRole('searchbox', {name: 'Search customers'})
    .fill('Zero One');
  await expect(page.getByText('Imp Zero One').first()).toBeVisible();
  expect(errors).toEqual([]);
});

test('IMP-02 · the same file twice updates and never duplicates', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await page.goto('/settings/import');
  const csv = `${CUSTOMERS_HEADER}Imp,Zero Two,${freshPhone()},,,,,,,\n`;

  await uploadFile(page, 'Customers', 'imp02-a.csv', csv);
  await expect(newest(page)).toContainText('1 created', {timeout: 30_000});
  await uploadFile(page, 'Customers', 'imp02-b.csv', csv);
  await expect(newest(page)).toContainText('imp02-b.csv');
  await expect(newest(page)).toContainText(
    '0 created, 0 updated, 1 unchanged, 0 failed',
    {timeout: 30_000},
  );
});

test('IMP-03 · bad rows are listed by row and column, and the rest lands', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await page.goto('/settings/import');
  const csv =
    CUSTOMERS_HEADER +
    `Imp,Zero Three,${freshPhone()},,,,,,,\n` +
    'Bad,Phone,12345,,,,,,,\n' +
    `Bad,Consent,${freshPhone()},,,,maybe,,,\n`;

  await uploadFile(page, 'Customers', 'imp03.csv', csv);
  await expect(newest(page)).toContainText(
    '1 created, 0 updated, 0 unchanged, 2 failed',
    {timeout: 30_000},
  );
  await newest(page).getByRole('button', {name: 'Show 2 problems'}).click();
  await expect(newest(page)).toContainText('Row 3:');
  await expect(newest(page)).toContainText('(column phone)');
  await expect(newest(page)).toContainText('sms_consent must be yes or no.');
});

test('IMP-04 · a leads file opens a lead per row', async ({signedInAs}) => {
  const page = await signedInAs('manager');
  await page.goto('/settings/import');
  const csv =
    LEADS_HEADER +
    `Imp,Zero Four,${freshPhone()},,website,IMP-04 Bronco,,sales2@mustang.test,\n`;

  await uploadFile(page, 'Open leads', 'imp04.csv', csv);
  await expect(newest(page)).toContainText('1 created', {timeout: 30_000});

  await page.goto('/leads');
  await page.getByLabel('Search leads').fill('IMP-04 Bronco');
  await expect(page.getByText('Imp Zero Four').first()).toBeVisible();
});

test('IMP-05 · only managers and admins can import', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  await page.goto('/settings');
  await expect(page.getByRole('link', {name: 'Import'})).toHaveCount(0);
  const api = await page.request.get('/api/v1/imports');
  expect(api.status(), 'a rep cannot list imports').toBe(403);
  await page.goto('/settings/import');
  await expect(page).not.toHaveURL(/\/settings\/import$/);
});
