import type {Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/** Vehicles the spec changes are created here, named after their case; the seeded MM-10xx stock is read-only. */
async function createVehicle(
  page: Page,
  body: Record<string, unknown>,
): Promise<{id: string; title: string}> {
  const response = await page.request.post('/api/v1/vehicles', {
    data: body,
    headers: {Origin: new URL(page.url()).origin},
  });
  expect(response.status(), 'creating a vehicle through the API').toBe(201);
  return (await response.json()) as {id: string; title: string};
}

const vehicleLinks = (page: Page) =>
  page.getByRole('link', {name: /MM-10\d\d/});

test('INV-01 · a rep browses and searches the inventory', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/inventory');

  await expect(
    page.getByRole('heading', {level: 1, name: 'Inventory'}),
  ).toBeVisible();
  await expect(vehicleLinks(page)).toHaveCount(25);
  await expect(page.getByText(/1–25 of \d+/)).toBeVisible();
  await page.getByRole('button', {name: 'Next page'}).click();
  await expect(page.getByText(/26–\d+ of \d+/)).toBeVisible();

  await page.getByLabel('Search inventory').fill('MM-1004');
  await expect(vehicleLinks(page)).toHaveCount(1);
  await expect(page.getByText('Call for price').first()).toBeVisible();

  await page.getByLabel('Search inventory').fill('ford mustang');
  await expect(vehicleLinks(page).first()).toContainText('Ford Mustang');
  expect(errors).toEqual([]);
});

test('INV-02 · filters narrow the list, and clearing them brings it back', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/inventory');
  await expect(vehicleLinks(page)).toHaveCount(25);

  await page.getByRole('button', {name: /Filters/}).click();
  await page.getByRole('combobox', {name: 'Condition'}).click();
  await page.getByRole('option', {name: 'New'}).click();
  await expect(page).toHaveURL(/condition=new/);
  await expect(page.getByLabel('1 filter applied')).toBeVisible();
  await expect(vehicleLinks(page)).toHaveCount(14);

  await page.getByRole('button', {name: 'Clear filters'}).click();
  await expect(page).not.toHaveURL(/condition=/);
  await expect(vehicleLinks(page)).toHaveCount(25);

  await page.getByLabel('Search inventory').fill('zzzz-no-such-vehicle');
  await expect(page.getByText('Nothing matches these filters.')).toBeVisible();
  await page.getByRole('button', {name: 'Show all'}).click();
  await expect(vehicleLinks(page)).toHaveCount(25);
  expect(errors).toEqual([]);
});

test('INV-03 · a rep adds a vehicle, and a stock number cannot be reused', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/inventory');

  async function fillAndSave() {
    await page.getByRole('button', {name: 'Add vehicle'}).click();
    const dialog = page.getByRole('dialog');
    await expect(
      dialog.getByRole('button', {name: 'Add vehicle'}),
    ).toBeDisabled();
    await dialog.getByLabel('Stock number').fill('INV03-1');
    await dialog.getByLabel('Year').fill('2025');
    await dialog.getByLabel('Make').fill('Toyota');
    await dialog.getByLabel('Model').fill('Tacoma');
    await dialog.getByLabel('Price ($)').fill('38500');
    await dialog.getByRole('button', {name: 'Add vehicle'}).click();
    return dialog;
  }

  await fillAndSave();
  await expect(page).toHaveURL(/\/inventory\/[0-9a-f-]{36}$/);
  await expect(
    page.getByRole('heading', {level: 1, name: '2025 Toyota Tacoma'}),
  ).toBeVisible();
  await expect(page.getByText('$38,500')).toBeVisible();

  await page.goto('/inventory');
  const dialog = await fillAndSave();
  await expect(
    dialog.getByText('This stock number is already in the inventory.'),
  ).toBeVisible();
  await expect(dialog).toBeVisible();
  expect(errors.filter((e) => !/409/.test(e))).toEqual([]);
});

test('INV-04 · a rep edits a vehicle and marks it sold', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/inventory');
  const vehicle = await createVehicle(page, {
    stock_number: 'INV04-1',
    year: 2024,
    make: 'Honda',
    model: 'Accord',
    condition: 'used',
    price_cents: 2800000,
  });

  await page.goto(`/inventory/${vehicle.id}`);
  await expect(
    page.getByRole('heading', {level: 1, name: '2024 Honda Accord'}),
  ).toBeVisible();
  await page.getByRole('button', {name: 'Edit'}).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByLabel('Stock number')).toHaveAttribute(
    'readonly',
    '',
  );
  await dialog.getByLabel('Price ($)').fill('26500');
  await dialog.getByLabel('Trim').fill('Sport');
  await dialog.getByRole('button', {name: 'Save'}).click();
  await expect(page.getByText('$26,500')).toBeVisible();
  await expect(page.getByText('Sport').first()).toBeVisible();

  await page.getByRole('combobox', {name: 'Status'}).click();
  await page.getByRole('option', {name: 'Sold'}).click();
  await expect(page.getByText('Marked Sold.')).toBeVisible();
  await page.reload();
  await expect(page.getByRole('combobox', {name: 'Status'})).toHaveText('Sold');
  expect(errors).toEqual([]);
});

test('INV-05 · a CSV upload imports the good rows and lists the bad ones', async ({
  signedInAs,
}) => {
  const page = await signedInAs('manager');
  await page.goto('/inventory');
  const header =
    'stock_number,year,make,model,trim,condition,price,mileage,status\n';
  const csv =
    header +
    'INV05-1,2023,Kia,Sorento,EX,used,"27,900",31000,available\n' +
    'INV05-2,2026,Kia,Telluride,SX,new,48500,12,hold\n' +
    'INV05-3,20x3,Kia,Soul,LX,used,15000,40000,available\n';

  await page.getByRole('button', {name: 'Upload CSV'}).click();
  const dialog = page.getByRole('dialog');
  await expect(
    dialog.getByRole('link', {name: 'Download a sample file'}),
  ).toBeVisible();
  await dialog.getByLabel('CSV file').setInputFiles({
    name: 'inv05.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from(csv),
  });
  await dialog.getByRole('button', {name: 'Upload'}).click();

  await expect(
    dialog.getByText('2 of 3 rows imported, 1 skipped.'),
  ).toBeVisible({timeout: 30_000});
  await expect(dialog.getByText('Row 4:')).toBeVisible();
  await expect(dialog.getByText(/year must be four digits/)).toBeVisible();
  await dialog.getByRole('button', {name: 'Done'}).click();

  await page.getByLabel('Search inventory').fill('INV05');
  await expect(page.getByRole('link', {name: /INV05-/})).toHaveCount(2);
});

test('INV-06 · another dealership’s vehicle is not found, and quick search finds ours', async ({
  signedInAs,
}) => {
  const other = await signedInAs('other');
  await other.goto('/inventory');
  const theirs = await createVehicle(other, {
    stock_number: 'INV06-OTHER',
    year: 2022,
    make: 'Mazda',
    model: 'CX-30',
    condition: 'used',
  });

  const page = await signedInAs('sales');
  await page.goto(`/inventory/${theirs.id}`);
  await expect(
    page.getByText('This vehicle is not in your inventory.'),
  ).toBeVisible();
  const api = await page.request.get(`/api/v1/vehicles/${theirs.id}`);
  expect(api.status()).toBe(404);

  await page.goto('/today');
  await expect(
    page.getByRole('heading', {level: 1, name: 'Today'}),
  ).toBeVisible();
  await page.keyboard.press('Control+k');
  await page
    .getByPlaceholder('Search by name, phone, email or stock number')
    .fill('MM-1002');
  await page
    .getByRole('option', {name: /Mustang/})
    .first()
    .click();
  await expect(page).toHaveURL(/\/inventory\/[0-9a-f-]{36}$/);
  await expect(page.getByText('MM-1002').first()).toBeVisible();
});
