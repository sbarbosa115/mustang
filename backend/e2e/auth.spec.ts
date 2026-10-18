import {
  ACCOUNTS,
  PASSWORD,
  consoleErrors,
  enterTotpCode,
  expect,
  test,
} from './support/test';

test.describe('AUTH-01 · sign in and out as each role', () => {
  // Each sign-in may wait up to 30 s for a fresh TOTP code.
  test.setTimeout(75_000);
  for (const account of ['sales', 'bdc', 'manager', 'admin'] as const) {
    test(`AUTH-01 · ${account}`, async ({page}) => {
      const errors = consoleErrors(page);
      await page.goto('/login');
      await page.getByLabel('Email').fill(ACCOUNTS[account]);
      await page.getByLabel('Password').fill(PASSWORD);
      await page.getByRole('button', {name: 'Sign in'}).click();
      await enterTotpCode(page);

      await expect(page).toHaveURL(/\/today$/);
      await expect(page.getByRole('heading', {name: 'Today'})).toBeVisible();

      await page.getByRole('button', {name: 'More'}).click();
      await page.getByRole('button', {name: 'Sign out'}).click();
      await expect(page).toHaveURL(/\/login$/);
      // The signed-in screen never comes back: reopening it goes to sign-in.
      await page.goto('/today');
      await expect(page).toHaveURL(/\/login$/);
      expect(errors).toEqual([]);
    });
  }
});

test('AUTH-02 · a rep cannot reach the manager screens', async ({
  signedInAs,
}) => {
  const page = await signedInAs('sales');
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/today$/);

  const response = await page.request.get('/api/v1/reports/kpis');
  expect(response.status()).toBe(403);
});

test('AUTH-03 · wrong credentials get one generic message', async ({page}) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill(ACCOUNTS.sales);
  await page.getByLabel('Password').fill('not-the-password');
  await page.getByRole('button', {name: 'Sign in'}).click();
  await expect(page.getByRole('alert')).toHaveText(
    'The email or password is not right.',
  );

  await page.getByLabel('Email').fill('nobody@mustang.test');
  await page.getByRole('button', {name: 'Sign in'}).click();
  await expect(page.getByRole('alert')).toHaveText(
    'The email or password is not right.',
  );
});
