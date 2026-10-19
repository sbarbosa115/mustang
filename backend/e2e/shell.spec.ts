import {consoleErrors, expect, test} from './support/test';

test('SHELL-01 · a rep moves between the phone tabs', async ({signedInAs}) => {
  const page = await signedInAs('sales');
  const errors = consoleErrors(page);
  await page.goto('/today');
  const tabs = page.getByRole('navigation', {name: 'Main'}).last();

  for (const [tab, heading] of [
    ['Inbox', 'Inbox'],
    ['Customers', 'Customers'],
    ['Inventory', 'Inventory'],
    ['Today', 'Today'],
  ] as const) {
    await tabs.getByRole('link', {name: tab}).click();
    await expect(
      page.getByRole('heading', {level: 1, name: heading}),
    ).toBeVisible();
  }
  await tabs.getByRole('button', {name: 'More'}).click();
  await expect(page.getByRole('link', {name: 'Deals'})).toBeVisible();
  await expect(page.getByRole('link', {name: 'Dashboard'})).toHaveCount(0);
  expect(errors).toEqual([]);
});

test.describe('desktop', () => {
  test.use({viewport: {width: 1280, height: 800}});

  test('SHELL-02 · a manager sees the dashboard and team in the sidebar', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    await page.goto('/today');
    const sidebar = page.getByRole('navigation', {name: 'Main'}).first();

    await sidebar.getByRole('link', {name: 'Dashboard'}).click();
    await expect(
      page.getByRole('heading', {level: 1, name: 'Dashboard'}),
    ).toBeVisible();
    await sidebar.getByRole('link', {name: 'Team'}).click();
    await expect(
      page.getByRole('heading', {level: 1, name: 'Team'}),
    ).toBeVisible();
  });

  test('SHELL-03 · quick search finds a seeded customer by phone', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('bdc');
    await page.goto('/today');
    await expect(
      page.getByRole('heading', {level: 1, name: 'Today'}),
    ).toBeVisible();
    await page.keyboard.press('Control+k');
    await page
      .getByPlaceholder('Search by name, phone, email or stock number')
      .fill('813-555-1001');

    await page.getByRole('option', {name: /Sarah Johnson/}).click();
    await expect(page).toHaveURL(/\/customers\/[0-9a-f-]{36}$/);
  });
});

test('SHELL-04 · the theme choice follows the user after a reload', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales2');
  await page.goto('/settings');
  await page.getByText('Dark', {exact: true}).click();
  await expect(page.locator('html')).toHaveClass(/dark/);
  await expect(page.getByText('Theme saved.')).toBeVisible();

  await page.evaluate(() => localStorage.clear());
  await page.reload();
  await expect(page.locator('html')).toHaveClass(/dark/);

  await page.getByText('Light', {exact: true}).click();
  await expect(page.locator('html')).not.toHaveClass(/dark/);
});

test('SHELL-05 · every route opens without a blank page or console error', async ({
  signedInAs,
}) => {
  const page = await signedInAs('admin');
  const errors = consoleErrors(page);
  for (const path of [
    '/today',
    '/inbox',
    '/customers',
    '/leads',
    '/deals',
    '/appointments',
    '/inventory',
    '/dashboard',
    '/team',
    '/settings',
    '/settings/security',
    '/settings/users',
    '/settings/templates',
    '/settings/import',
    '/settings/routing',
    '/settings/suppressions',
    '/settings/audit',
    '/does-not-exist',
  ]) {
    await page.goto(path);
    await expect(page.locator('main h1, main p').first(), path).toBeVisible();
  }
  expect(errors).toEqual([]);
});
