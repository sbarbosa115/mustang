import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * LEAD-01 – 06. Cases that create a customer use their own phone number (813-555-03xx) and name, so the seeded
 * customers stay as the seed made them; LEAD-02 only adds a lead to Sarah Johnson.
 */

async function pick(page: Page, label: string, option: string) {
  await page.getByRole('dialog').getByLabel(label).click();
  await page.getByRole('option', {name: option, exact: true}).click();
}

/** Opens "Capture lead" from /leads and fills it; the caller submits. */
async function openCapture(page: Page) {
  await page.goto('/leads');
  await page.getByRole('button', {name: 'Capture lead'}).first().click();
  return page.getByRole('dialog', {name: 'Capture a lead'});
}

async function captureNew(
  page: Page,
  first: string,
  last: string,
  phone: string,
  source = 'Walk-in',
) {
  const dialog = await openCapture(page);
  await pick(page, 'Source', source);
  await dialog.getByLabel('Phone').fill(phone);
  await dialog.getByLabel('First name').fill(first);
  await dialog.getByLabel('Last name').fill(last);
  await dialog.getByRole('button', {name: 'Capture lead'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: `Lead from ${first} ${last}`}),
  ).toBeVisible();
}

test('LEAD-01 · capture a walk-in for a new customer', async ({signedInAs}) => {
  const page = await signedInAs('bdc');
  const errors = consoleErrors(page);
  const dialog = await openCapture(page);
  await pick(page, 'Source', 'Walk-in');
  await dialog.getByLabel('Phone').fill('813-555-0301');
  await dialog.getByLabel('First name').fill('LEAD-01');
  await dialog.getByLabel('Last name').fill('Test');
  await dialog.getByLabel('Vehicle of interest').fill('2025 RAV4 Hybrid');
  await dialog.getByRole('button', {name: 'Capture lead'}).click();

  await expect(
    page.getByRole('heading', {level: 1, name: 'Lead from LEAD-01 Test'}),
  ).toBeVisible();
  await expect(page.getByText('Assigned', {exact: true})).toBeVisible();
  await expect(page.getByText('Walk-in', {exact: true})).toBeVisible();
  await expect(page.getByText('2025 RAV4 Hybrid')).toBeVisible();
  // Whoever is next in the rotation (earlier specs add and move reps), but someone, never "Unassigned".
  const assignee = page
    .locator('dl > div')
    .filter({has: page.getByText('Rep', {exact: true})});
  await expect(assignee.locator('dd')).not.toHaveText('Unassigned');
  await expect(assignee.locator('dd')).not.toBeEmpty();

  await page.getByRole('link', {name: 'LEAD-01 Test'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: 'LEAD-01 Test'}),
  ).toBeVisible();
  await expect(page.getByText('(813) 555-0301')).toBeVisible();
  expect(errors).toEqual([]);
});

test('LEAD-02 · a lead for a customer on file is added to that customer', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const dialog = await openCapture(page);
  await pick(page, 'Source', 'Phone call');
  await dialog.getByLabel('Phone').fill('813.555.1001');
  await expect(
    dialog.getByText(
      'Adds the lead to Sarah Johnson, who is already a customer.',
    ),
  ).toBeVisible();
  await dialog.getByRole('button', {name: 'Capture lead'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: 'Lead from Sarah Johnson'}),
  ).toBeVisible();

  const found = await page.request.get('/api/v1/customers?q=813-555-1001');
  expect(((await found.json()) as {total: number}).total).toBe(1);
});

test('LEAD-03 · the form explains what is missing', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  const dialog = await openCapture(page);
  const submit = dialog.getByRole('button', {name: 'Capture lead'});
  await expect(submit).toBeDisabled();
  await dialog.getByLabel('Email').fill('lead03@example.com');
  await expect(
    submit,
    'an email without a source is not enough',
  ).toBeDisabled();
  await pick(page, 'Source', 'Walk-in');
  await expect(submit).toBeEnabled();
  await submit.click();
  await expect(
    dialog.getByText('This is a new customer: enter a first and a last name.'),
  ).toBeVisible();
});

test.describe('desktop', () => {
  test.use({viewport: {width: 1280, height: 800}});

  test('LEAD-04 · round-robin order in Settings, Lead routing', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    await page.goto('/settings/routing');
    await expect(
      page.getByRole('heading', {name: 'Lead routing'}),
    ).toBeVisible();
    const list = page.getByRole('list').filter({hasText: 'Sam Patel'});
    await expect(list).toContainText('Jordan Reyes');

    // Put the order to Sam Patel, then Jordan Reyes, whatever earlier runs left: other salespeople (an earlier
    // spec adds some) are taken out of the rotation, and Sam is moved up until he is number 1.
    for (const name of ['Sam Patel', 'Jordan Reyes']) {
      const box = page.getByRole('checkbox', {name: `Include ${name}`});
      if (!(await box.isChecked())) await box.click();
    }
    for (const box of await page
      .getByRole('checkbox', {name: /^Include /})
      .all()) {
      const label = (await box.getAttribute('aria-label')) ?? '';
      if (/Sam Patel|Jordan Reyes/.test(label)) continue;
      if (await box.isChecked()) await box.click();
    }
    const sam = list.getByRole('listitem').filter({hasText: 'Sam Patel'});
    for (
      let i = 0;
      i < 10 && !(await sam.textContent())?.startsWith('1.');
      i++
    ) {
      await page.getByRole('button', {name: 'Move Sam Patel up'}).click();
    }
    await expect(sam).toContainText(/^1\./);
    const save = page.getByRole('button', {name: 'Save order'});
    if (await save.isEnabled()) {
      await save.click();
      await expect(page.getByText('Routing saved.')).toBeVisible();
    } else {
      // Already in this order: saving again restarts it from Sam Patel.
      await page.getByRole('checkbox', {name: 'Include Jordan Reyes'}).click();
      await save.click();
      await page.getByRole('checkbox', {name: 'Include Jordan Reyes'}).click();
      await save.click();
    }
    await expect(page.getByText('Next lead goes to Sam Patel.')).toBeVisible();

    await captureNew(page, 'LEAD-04', 'First', '813-555-0304', 'Phone call');
    await expect(page.getByText('Sam Patel').first()).toBeVisible();
    await captureNew(page, 'LEAD-04', 'Second', '813-555-0305', 'Phone call');
    await expect(page.getByText('Jordan Reyes').first()).toBeVisible();
  });

  test('LEAD-05 · hand a lead to another rep, then close it', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    await captureNew(page, 'LEAD-05', 'Test', '813-555-0306');

    await page.getByLabel('Hand to').click();
    await page.getByRole('option', {name: 'Brianna Diaz'}).click();
    await page.getByRole('button', {name: 'Assign'}).click();
    await expect(page.getByText('Lead handed to Brianna Diaz.')).toBeVisible();

    await page.getByRole('button', {name: 'Close lead'}).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Close the lead from LEAD-05 Test?');
    await confirm.getByRole('button', {name: 'Close lead'}).click();
    await expect(page.getByText('Closed', {exact: true})).toBeVisible();
    await expect(page.getByRole('button', {name: 'Close lead'})).toHaveCount(0);
    await expect(page.getByLabel('Hand to')).toBeDisabled();
  });

  test('LEAD-06 · search the list; another dealership cannot open a lead', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('bdc');
    await page.goto('/leads?q=LEAD-05');
    const row = page.getByRole('row', {name: /LEAD-05 Test/});
    await expect(row).toContainText('Closed');

    await page.goto('/leads?q=zzz-nobody&status=closed');
    await expect(
      page.getByText('Nothing matches these filters.'),
    ).toBeVisible();
    await page.getByRole('button', {name: 'Show all'}).click();
    await expect(page).toHaveURL(/\/leads$/);
    await expect(
      page.getByRole('searchbox', {name: 'Search leads'}),
    ).toHaveValue('');

    const found = await page.request.get('/api/v1/leads?q=LEAD-05');
    const lead = ((await found.json()) as {items: Array<{id: string}>})
      .items[0];
    const outsider = await signedInAs('other');
    await outsider.goto(`/leads/${lead?.id}`);
    await expect(
      outsider.getByText(
        'This lead does not exist or is not in your dealership.',
      ),
    ).toBeVisible();
  });
});
