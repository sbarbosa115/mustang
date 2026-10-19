import type {Page} from '@playwright/test';
import {
  ACCOUNTS,
  PASSWORD,
  SEED_RECOVERY_CODES,
  consoleErrors,
  enterTotpCode,
  expect,
  test,
} from './support/test';

// A code step may wait for the next 30-second code (each code is accepted once per account).
test.describe.configure({timeout: 75_000});

async function passwordStep(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(PASSWORD);
  await page.getByRole('button', {name: 'Sign in'}).click();
}

test('AUTH-05 · sign-in asks for the code from the app', async ({page}) => {
  const errors = consoleErrors(page);
  // "other" signs in through the form here only, so its code is rarely used already.
  await passwordStep(page, ACCOUNTS.other);

  await expect(
    page.getByRole('heading', {name: 'Enter your code'}),
  ).toBeVisible();
  const field = page.getByLabel('Code from your authenticator app');
  await field.fill('000000');
  await page.getByRole('button', {name: 'Verify'}).click();
  await expect(page.getByRole('alert')).toHaveText(
    'That code is not right. Check the app and try again.',
  );

  await enterTotpCode(page);
  await expect(page).toHaveURL(/\/today$/);
  await expect(page.getByRole('heading', {name: 'Today'})).toBeVisible();
  expect(errors).toEqual([]);
});

test('AUTH-06 · a recovery code signs in once', async ({
  page,
  playwright,
  baseURL,
}) => {
  await passwordStep(page, ACCOUNTS.bdc);
  await page.getByRole('button', {name: 'Use a recovery code instead'}).click();
  await page.getByLabel('Recovery code').fill(SEED_RECOVERY_CODES[0]);
  await page.getByRole('button', {name: 'Verify'}).click();
  await expect(page).toHaveURL(/\/today$/);

  await page.goto('/settings/security');
  await expect(page.getByText('On', {exact: true})).toBeVisible();
  await expect(page.getByText('7 recovery codes left')).toBeVisible();

  // The same code again, from another browser: refused.
  const origin = new URL(baseURL as string).origin;
  const other = await playwright.request.newContext({
    baseURL,
    extraHTTPHeaders: {'X-Forwarded-For': '10.78.0.6'},
  });
  const login = await other.post('/api/v1/auth/login', {
    data: {email: ACCOUNTS.bdc, password: PASSWORD},
    headers: {Origin: origin},
  });
  expect(login.status()).toBe(200);
  const again = await other.post('/api/v1/auth/mfa/verify', {
    data: {recovery_code: SEED_RECOVERY_CODES[0]},
    headers: {Origin: origin},
  });
  expect(again.status()).toBe(422);
  expect((await again.json()).error).toBe('mfa_invalid_code');
  await other.dispose();
});

test('AUTH-07 · a password alone reaches nothing', async ({
  playwright,
  baseURL,
}) => {
  const origin = new URL(baseURL as string).origin;
  const request = await playwright.request.newContext({
    baseURL,
    extraHTTPHeaders: {'X-Forwarded-For': '10.78.0.7'},
  });
  const login = await request.post('/api/v1/auth/login', {
    data: {email: ACCOUNTS.sales2, password: PASSWORD},
    headers: {Origin: origin},
  });
  expect((await login.json()).next).toBe('mfa');

  const customers = await request.get('/api/v1/customers');
  expect(customers.status()).toBe(403);
  expect((await customers.json()).error).toBe('mfa_required');
  const status = await request.get('/api/v1/auth/mfa');
  expect(status.status()).toBe(200);
  expect((await status.json()).verified).toBe(false);
  await request.dispose();
});

test('AUTH-08 · only an admin of the dealership resets MFA', async ({
  signedInAs,
}) => {
  const manager = await signedInAs('manager');
  const users = await manager.request.get('/api/v1/users');
  const {items} = (await users.json()) as {
    items: Array<{id: string; email: string; mfa_enrolled: boolean}>;
  };
  const target = items.find((u) => u.email === ACCOUNTS.sales2);
  expect(target?.mfa_enrolled).toBe(true);

  const byManager = await manager.request.delete(
    `/api/v1/users/${target?.id}/mfa`,
  );
  expect(byManager.status()).toBe(403);

  const other = await signedInAs('other');
  const byOtherDealership = await other.request.delete(
    `/api/v1/users/${target?.id}/mfa`,
  );
  expect(byOtherDealership.status()).toBe(404);
});
