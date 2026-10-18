import fs from 'node:fs';
import path from 'node:path';
import {
  test as base,
  expect,
  type APIRequestContext,
  type Browser,
  type BrowserContext,
  type Page,
} from '@playwright/test';
import {generateSync} from 'otplib';

export {expect};

/** The seed accounts (mustang:seed-demo). Dev only. */
export const ACCOUNTS = {
  admin: 'admin@mustang.test',
  manager: 'manager@mustang.test',
  bdc: 'bdc@mustang.test',
  sales: 'sales@mustang.test',
  sales2: 'sales2@mustang.test',
  other: 'other@mustang.test',
} as const;
export type Account = keyof typeof ACCOUNTS;
export const PASSWORD = 'Mustang-dev-1';
/** Every seed user's TOTP secret once MFA is enrolled by the seed (item "mfa"). */
export const SEED_TOTP_SECRET = 'MUSTANGDEVTOTPSECRET234567';

const AUTH_DIR = path.join(process.cwd(), 'e2e', '.results', 'auth');
let addresses = 0;

/**
 * A visitor address of its own for every test: sign-in is rate-limited per IP and email (the stack trusts
 * X-Forwarded-For during a smoke run), so one test's attempts never use up another's allowance.
 */
function nextAddress(): string {
  addresses += 1;
  return `10.77.${Math.floor(addresses / 250) % 250}.${(addresses % 250) + 1}`;
}

/**
 * Signs in through the API the way the app does: email and password, then the TOTP step when the API asks for it.
 */
export async function signInThroughApi(
  request: APIRequestContext,
  baseURL: string,
  email: string,
): Promise<void> {
  const origin = new URL(baseURL).origin;
  const login = await request.post('/api/v1/auth/login', {
    data: {email, password: PASSWORD},
    headers: {Origin: origin},
  });
  expect(login.status(), `signing in as ${email}`).toBe(200);
  const {next} = (await login.json()) as {next: 'ok' | 'mfa' | 'enroll'};
  if (next === 'mfa') {
    const verify = await request.post('/api/v1/auth/mfa/verify', {
      data: {code: generateSync({secret: SEED_TOTP_SECRET})},
      headers: {Origin: origin},
    });
    expect(verify.status(), `the TOTP step for ${email}`).toBe(200);
  } else {
    expect(next, `${email} must not need MFA enrollment in a smoke run`).toBe(
      'ok',
    );
  }
}

/** A saved session per account for the whole run. */
async function sessionOf(
  browser: Browser,
  baseURL: string,
  account: Account,
): Promise<string> {
  const file = path.join(AUTH_DIR, `${account}.json`);
  if (fs.existsSync(file)) {
    return file;
  }
  const context = await browser.newContext({
    baseURL,
    extraHTTPHeaders: {'X-Forwarded-For': nextAddress()},
  });
  await signInThroughApi(context.request, baseURL, ACCOUNTS[account]);
  fs.mkdirSync(AUTH_DIR, {recursive: true});
  await context.storageState({path: file});
  await context.close();
  return file;
}

interface Fixtures {
  /** A page signed in as a seed account. */
  signedInAs: (account: Account) => Promise<Page>;
}

export const test = base.extend<Fixtures>({
  // Every test is its own visitor (see nextAddress).
  // (Playwright wants the first argument destructured, even when nothing of it is used.)
  extraHTTPHeaders: async ({locale: _locale}, provide) => {
    await provide({'X-Forwarded-For': nextAddress()});
  },
  signedInAs: async (
    {browser, baseURL, viewport, locale, timezoneId},
    provide,
  ) => {
    const contexts: BrowserContext[] = [];
    await provide(async (account) => {
      const storageState = await sessionOf(browser, baseURL as string, account);
      const context = await browser.newContext({
        baseURL,
        viewport,
        locale,
        timezoneId,
        storageState,
        extraHTTPHeaders: {'X-Forwarded-For': nextAddress()},
      });
      contexts.push(context);
      return context.newPage();
    });
    await Promise.all(contexts.map((c) => c.close()));
  },
});

/**
 * A page's console errors, collected from now on: `expect(errors).toEqual([])` at the end of a view's test.
 * The browser's own "Failed to load resource … 401" is left out: the app asks /api/v1/me on load to learn whether
 * someone is signed in, and a visitor's answer is 401 by design.
 */
export function consoleErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (
      message.type() === 'error' &&
      !/Failed to load resource: .* 401/.test(message.text())
    ) {
      errors.push(message.text());
    }
  });
  page.on('pageerror', (error) => errors.push(error.message));
  return errors;
}
