import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * CUS-01 – 08. Each case that changes data creates its own customer, named after the case, so the seeded customers
 * stay as the seed made them.
 */

/** Opens "Add customer", fills the form and submits; lands on the new customer's record. */
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

async function openMenu(page: Page, item: string) {
  await page.getByRole('button', {name: 'Customer actions'}).click();
  await page.getByRole('menuitem', {name: item}).click();
}

test('CUS-01 · create a customer from the list', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await addCustomer(page, 'CUS-01', 'Test', '(813) 555-0201');

  // The phone is shown the way people read it; the creator is Sales 1.
  await expect(page.getByText('(813) 555-0201')).toBeVisible();
  await expect(page.getByText('Sam Patel')).toBeVisible();

  await page.goto('/customers?q=CUS-01');
  await expect(
    page.getByRole('link', {name: 'CUS-01 Test'}).last(),
  ).toBeVisible();
  expect(errors).toEqual([]);
});

test.describe('desktop', () => {
  test.use({viewport: {width: 1280, height: 800}});

  test('CUS-02 · the top bar opens the form, which explains what is missing', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('bdc');
    await page.goto('/today');
    await page.getByRole('link', {name: 'Add customer'}).click();
    await expect(page).toHaveURL(/\/customers\?new=1$/);

    const dialog = page.getByRole('dialog', {name: 'Add customer'});
    const submit = dialog.getByRole('button', {name: 'Add customer'});
    await expect(submit).toBeDisabled();

    await dialog.getByLabel('First name').fill('CUS-02');
    await dialog.getByLabel('Last name').fill('Test');
    await dialog.getByLabel('Phone').fill('813');
    await dialog.getByLabel('Phone').fill('');
    await expect(
      dialog.getByText('Enter a phone number or an email.'),
    ).toBeVisible();
    await expect(submit).toBeDisabled();

    await dialog.getByLabel('Email', {exact: true}).fill('not-an-email');
    await expect(
      dialog.getByText('Enter a valid email address.'),
    ).toBeVisible();
    await expect(submit).toBeDisabled();

    await dialog.getByLabel('Email', {exact: true}).fill('cus02@example.com');
    await expect(submit).toBeEnabled();
    await submit.click();
    await expect(
      page.getByRole('heading', {level: 1, name: 'CUS-02 Test'}),
    ).toBeVisible();
    await expect(page.getByText('cus02@example.com')).toBeVisible();
  });
});

test('CUS-03 · a phone already on file is refused and the existing customer is offered', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/customers');
  await page.getByRole('button', {name: 'Add customer'}).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('First name').fill('CUS-03');
  await dialog.getByLabel('Last name').fill('Duplicate');
  // Sarah Johnson's number, written another way.
  await dialog.getByLabel('Phone').fill('813-555-1001');
  await dialog.getByRole('button', {name: 'Add customer'}).click();

  await expect(dialog.getByText(/phone number already exists/)).toBeVisible();
  await dialog.getByRole('link', {name: 'Open that customer'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: 'Sarah Johnson'}),
  ).toBeVisible();
});

test('CUS-04 · search the list, and clear a search that finds nothing', async ({
  signedInAs,
}) => {
  const page = await signedInAs('bdc');
  const errors = consoleErrors(page);
  await page.goto('/customers');
  const search = page.getByRole('searchbox', {name: 'Search customers'});
  // The list is ordered by last update, so no seeded customer is promised on page 1: count rows instead.
  const rows = page.locator('a[href^="/customers/"]');
  await expect(rows.first()).toBeVisible();
  const all = await rows.count();
  expect(all).toBeGreaterThan(1);

  await search.fill('813-555-1002');
  await expect(
    page.getByRole('link', {name: 'Michael Williams'}).last(),
  ).toBeVisible();
  await expect(rows.filter({visible: true})).toHaveCount(1);
  await expect(page.getByRole('link', {name: 'Sarah Johnson'})).toHaveCount(0);

  await search.fill('zzz-nobody');
  await expect(page.getByText('Nothing matches these filters.')).toBeVisible();
  await page.getByRole('button', {name: 'Show all'}).click();
  await expect(search).toHaveValue('');
  await expect(rows.first()).toBeVisible();
  await expect(page.getByText('Nothing matches these filters.')).toHaveCount(0);

  // A customer opens from the list.
  await search.fill('Sarah Johnson');
  await page.getByRole('link', {name: 'Sarah Johnson'}).last().click();
  await expect(
    page.getByRole('heading', {level: 1, name: 'Sarah Johnson'}),
  ).toBeVisible();
  await expect(page.getByRole('tab', {name: 'Timeline'})).toBeVisible();
  expect(errors).toEqual([]);
});

test('CUS-05 · edit a customer, add and remove an email', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await addCustomer(page, 'CUS-05', 'Test', '813-555-0205');

  await openMenu(page, 'Edit details');
  const edit = page.getByRole('dialog', {name: 'Edit customer'});
  await edit.getByLabel('First name').fill('CUS-05 Renamed');
  await edit.getByRole('button', {name: 'Save'}).click();
  await expect(
    page.getByRole('heading', {level: 1, name: 'CUS-05 Renamed Test'}),
  ).toBeVisible();

  await openMenu(page, 'Manage contacts');
  const contacts = page.getByRole('dialog', {name: 'Contacts'});
  await contacts.getByRole('button', {name: 'Add contact'}).click();
  await contacts.getByLabel('Type').click();
  await page.getByRole('option', {name: 'Email'}).click();
  await contacts.getByLabel('Value').fill('Cus05@Example.com');
  await contacts.getByRole('button', {name: 'Save'}).click();
  // Stored in lowercase, and the first email is the primary one.
  await expect(contacts.getByText('cus05@example.com')).toBeVisible();
  await expect(contacts.getByText('Primary')).toHaveCount(2);

  await contacts
    .getByRole('button', {name: 'Remove cus05@example.com'})
    .click();
  await page
    .getByRole('alertdialog')
    .getByRole('button', {name: 'Remove'})
    .click();
  await expect(contacts.getByText('cus05@example.com')).toHaveCount(0);
});

test('CUS-06 · assign the team and record a vehicle the customer owns', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await addCustomer(page, 'CUS-06', 'Test', '813-555-0206');

  await openMenu(page, 'Change team');
  const team = page.getByRole('dialog', {name: 'Change team'});
  await team.getByLabel('Sales 2').click();
  await page.getByRole('option', {name: 'Jordan Reyes'}).click();
  await team.getByLabel('BDC').click();
  await page.getByRole('option', {name: 'Brianna Diaz'}).click();
  await team.getByRole('button', {name: 'Save'}).click();
  // The team shows as definitions; the dialog's own (native) options also carry these names.
  await expect(
    page.getByRole('definition').filter({hasText: 'Jordan Reyes'}),
  ).toBeVisible();
  await expect(
    page.getByRole('definition').filter({hasText: 'Brianna Diaz'}),
  ).toBeVisible();

  await page.getByRole('button', {name: 'Add vehicle'}).click();
  const vehicle = page.getByRole('dialog', {name: 'Add a vehicle'});
  await vehicle.getByLabel('Year').fill('2018');
  await vehicle.getByLabel('Make').fill('Toyota');
  await vehicle.getByLabel('Model').fill('Camry');
  await vehicle.getByRole('button', {name: 'Save'}).click();
  await expect(page.getByText('2018 Toyota Camry')).toBeVisible();

  await page
    .getByRole('button', {name: 'Remove vehicle 2018 Toyota Camry'})
    .click();
  await page
    .getByRole('alertdialog')
    .getByRole('button', {name: 'Remove vehicle'})
    .click();
  await expect(page.getByText('2018 Toyota Camry')).toHaveCount(0);
});

test('CUS-07 · notes are listed newest first with their author', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await addCustomer(page, 'CUS-07', 'Test', '813-555-0207');

  const add = page.getByRole('button', {name: 'Add note'});
  await expect(add).toBeDisabled();
  await page.getByLabel('New note').fill('First note: wants a third row.');
  await add.click();
  // The saved note in the list (the text in the box matches getByText too, so wait for the box to empty).
  await expect(page.getByLabel('New note')).toHaveValue('');
  await expect(
    page.locator('li', {hasText: 'First note: wants a third row.'}),
  ).toBeVisible();
  await page.getByLabel('New note').fill('Second note: call after 5 pm.');
  await add.click();

  const notes = page.locator('li', {hasText: 'note:'});
  await expect(notes).toHaveCount(2);
  await expect(notes.first()).toContainText('Second note: call after 5 pm.');
  await expect(notes.first()).toContainText('Sam Patel');
});

test('CUS-08 · archive a customer; another dealership cannot open the record', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await addCustomer(page, 'CUS-08', 'Test', '813-555-0208');

  await openMenu(page, 'Archive customer');
  await expect(page.getByRole('alertdialog')).toContainText(
    'Archive CUS-08 Test?',
  );
  await page
    .getByRole('alertdialog')
    .getByRole('button', {name: 'Archive customer'})
    .click();
  await expect(page.getByText('Archived', {exact: true})).toBeVisible();

  await page.goto('/customers?q=CUS-08');
  await expect(page.getByText('Nothing matches these filters.')).toBeVisible();
  await page.goto('/customers?q=CUS-08&scope=archived');
  await expect(
    page.getByRole('link', {name: 'CUS-08 Test'}).last(),
  ).toBeVisible();

  // Sarah Johnson's record, opened by someone from Other Motors.
  const found = await page.request.get('/api/v1/customers?q=Sarah');
  const sarah = ((await found.json()) as {items: Array<{id: string}>}).items[0];
  const outsider = await signedInAs('other');
  await outsider.goto(`/customers/${sarah?.id}`);
  await expect(
    outsider.getByText(
      'This customer does not exist or is not in your dealership.',
    ),
  ).toBeVisible();
});
