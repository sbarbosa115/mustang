import type {APIRequestContext, Page} from '@playwright/test';
import {generateSync} from 'otplib';
import {consoleErrors, expect, test} from './support/test';

/**
 * USR-01 – 05. The seed accounts stay as the seed made them (other specs sign in with them): every case adds users
 * of its own, with an email made unique by the run, and changes only those.
 */

// Admin screens are desktop first.
test.use({viewport: {width: 1280, height: 800}});

const NEW_PASSWORD = 'smoke-test-password-1';
let counter = 0;
const run = Date.now().toString(36);
/** A last name of this run: rows from earlier runs (the data is kept) never match. */
const LAST = `Smoke${run}`;

function emailFor(label: string): string {
  counter += 1;
  return `${label}.${run}${counter}@mustang.test`;
}

async function addThroughApi(
  page: Page,
  label: string,
  role = 'sales',
): Promise<{id: string; email: string; name: string}> {
  const email = emailFor(label);
  const response = await page.request.post('/api/v1/users', {
    data: {
      email,
      first_name: label.toUpperCase(),
      last_name: LAST,
      role,
      password: NEW_PASSWORD,
    },
    headers: {Origin: new URL(page.url()).origin},
  });
  expect(response.status(), 'adding a user through the API').toBe(201);
  const body = (await response.json()) as {id: string; name: string};
  return {id: body.id, email, name: body.name};
}

/** A signed-in session of a new user, who has set up two-step sign-in through the API (as the app would). */
async function signInNewUser(
  request: APIRequestContext,
  origin: string,
  email: string,
  enroll = true,
) {
  const login = await request.post('/api/v1/auth/login', {
    data: {email, password: NEW_PASSWORD},
    headers: {Origin: origin},
  });
  const body = (await login.json()) as {next?: string; error?: string};
  if (enroll && login.status() === 200) {
    expect(body.next, 'a new user enrolls at first sign-in').toBe('enroll');
    const enrolled = await request.post('/api/v1/auth/mfa/enroll', {
      headers: {Origin: origin},
    });
    const {secret} = (await enrolled.json()) as {secret: string};
    const confirmed = await request.post('/api/v1/auth/mfa/confirm', {
      data: {code: generateSync({secret})},
      headers: {Origin: origin},
    });
    expect(confirmed.status(), 'confirming the new factor').toBe(200);
  }
  return {status: login.status(), body};
}

async function rowOf(page: Page, name: string) {
  await page.goto('/settings/users');
  // The search matches the first name, the last name or the email, not the two together: search the last name.
  await page.getByLabel('Search users').fill(name.split(' ').pop() as string);
  // The search applies after a short pause: wait for it so the list does not change under the next click.
  await expect(page).toHaveURL(/[?&]q=/);
  const row = page.getByRole('row', {name: new RegExp(name)});
  await expect(row).toBeVisible();
  return row;
}

test('USR-01 · an admin adds a user, who sets up two-step sign-in at first sign-in', async ({
  signedInAs,
  playwright,
  baseURL,
}) => {
  const page = await signedInAs('admin');
  const errors = consoleErrors(page);
  const email = emailFor('usr01');
  await page.goto('/settings/users');
  await page.getByRole('button', {name: 'Add user'}).click();
  const dialog = page.getByRole('dialog', {name: 'Add a user'});
  await dialog.getByLabel('First name').fill('USR01');
  await dialog.getByLabel('Last name').fill(LAST);
  await dialog.getByLabel('Email').fill(email);
  await dialog.getByLabel('Role').click();
  await page.getByRole('option', {name: 'BDC', exact: true}).click();
  await dialog.getByLabel('Temporary password').fill(NEW_PASSWORD);
  await dialog.getByRole('button', {name: 'Add user'}).click();

  await expect(page.getByText(`USR01 ${LAST} was added.`)).toBeVisible();
  const row = await rowOf(page, `USR01 ${LAST}`);
  await expect(row.getByText('BDC', {exact: true})).toBeVisible();
  await expect(row.getByText('Active', {exact: true})).toBeVisible();
  await expect(row.getByText('Not set up')).toBeVisible();

  // The new user signs in with the password and is asked to set up the second factor.
  const visitor = await playwright.request.newContext({
    baseURL,
    extraHTTPHeaders: {'X-Forwarded-For': '10.79.0.1'},
  });
  const {status, body} = await signInNewUser(
    visitor,
    new URL(baseURL as string).origin,
    email,
    false,
  );
  expect(status).toBe(200);
  expect(body.next).toBe('enroll');
  await visitor.dispose();
  expect(errors).toEqual([]);
});

test('USR-02 · an admin edits a name and a role', async ({signedInAs}) => {
  const page = await signedInAs('admin');
  const errors = consoleErrors(page);
  await page.goto('/settings/users');
  const user = await addThroughApi(page, 'usr02');
  const row = await rowOf(page, `USR02 ${LAST}`);
  await row.getByRole('button', {name: `Actions for ${user.name}`}).click();
  await page.getByRole('menuitem', {name: 'Edit'}).click();

  const dialog = page.getByRole('dialog', {name: `Edit ${user.name}`});
  await expect(
    dialog.getByRole('button', {name: 'Save changes'}),
  ).toBeDisabled();
  await dialog.getByLabel('First name').fill('USR02b');
  await dialog.getByLabel('Role').click();
  await page.getByRole('option', {name: 'Manager', exact: true}).click();
  await dialog.getByRole('button', {name: 'Save changes'}).click();

  await expect(page.getByText(`USR02b ${LAST} was updated.`)).toBeVisible();
  const changed = await rowOf(page, `USR02b ${LAST}`);
  await expect(changed.getByText('Manager', {exact: true})).toBeVisible();
  expect(errors).toEqual([]);
});

test('USR-03 · the form explains what is missing, and a taken email is refused', async ({
  signedInAs,
}) => {
  const page = await signedInAs('admin');
  await page.goto('/settings/users');
  await page.getByRole('button', {name: 'Add user'}).click();
  const dialog = page.getByRole('dialog', {name: 'Add a user'});
  const submit = dialog.getByRole('button', {name: 'Add user'});
  await expect(submit).toBeDisabled();

  await dialog.getByLabel('First name').fill('USR03');
  await dialog.getByLabel('Last name').fill(LAST);
  await dialog.getByLabel('Email').fill('not-an-email');
  await expect(dialog.getByText('Enter a valid email address.')).toBeVisible();
  await dialog.getByLabel('Temporary password').fill('short');
  await expect(dialog.getByText('Use at least 12 characters.')).toBeVisible();
  await expect(submit).toBeDisabled();

  await dialog.getByLabel('Email').fill('Sales@mustang.test');
  await dialog.getByLabel('Temporary password').fill(NEW_PASSWORD);
  await expect(submit).toBeEnabled();
  await submit.click();
  await expect(
    dialog.getByText('Another account already uses this email.'),
  ).toBeVisible();
  await expect(dialog).toBeVisible();
});

test('USR-04 · deactivating asks first, ends their session and blocks sign-in; activating restores it', async ({
  signedInAs,
  playwright,
  baseURL,
}) => {
  const page = await signedInAs('admin');
  const errors = consoleErrors(page);
  await page.goto('/settings/users');
  const user = await addThroughApi(page, 'usr04');
  const origin = new URL(baseURL as string).origin;
  const theirs = await playwright.request.newContext({
    baseURL,
    extraHTTPHeaders: {'X-Forwarded-For': '10.79.0.4'},
  });
  await signInNewUser(theirs, origin, user.email);
  expect((await theirs.get('/api/v1/me')).status(), 'their session works').toBe(
    200,
  );

  const row = await rowOf(page, `USR04 ${LAST}`);
  await row.getByRole('button', {name: `Actions for ${user.name}`}).click();
  await page.getByRole('menuitem', {name: 'Deactivate'}).click();
  const confirm = page.getByRole('alertdialog', {
    name: `Deactivate ${user.name}?`,
  });
  await expect(confirm).toBeVisible();
  await confirm.getByRole('button', {name: 'Cancel'}).click();
  await expect(
    (await rowOf(page, `USR04 ${LAST}`)).getByText('Active', {exact: true}),
  ).toBeVisible();

  const again = await rowOf(page, `USR04 ${LAST}`);
  await again.getByRole('button', {name: `Actions for ${user.name}`}).click();
  await page.getByRole('menuitem', {name: 'Deactivate'}).click();
  await page
    .getByRole('alertdialog')
    .getByRole('button', {name: `Deactivate ${user.name}`})
    .click();
  await expect(page.getByText(`${user.name} was deactivated.`)).toBeVisible();
  await expect(
    (await rowOf(page, `USR04 ${LAST}`)).getByText('Deactivated'),
  ).toBeVisible();

  expect(
    (await theirs.get('/api/v1/me')).status(),
    'their open session ends on its next request',
  ).toBe(401);
  const blocked = await signInNewUser(theirs, origin, user.email, false);
  expect(blocked.status, 'a deactivated user cannot sign in').toBe(401);

  const reactivate = await rowOf(page, `USR04 ${LAST}`);
  await reactivate
    .getByRole('button', {name: `Actions for ${user.name}`})
    .click();
  await page.getByRole('menuitem', {name: 'Activate'}).click();
  await expect(page.getByText(`${user.name} was activated.`)).toBeVisible();
  const back = await signInNewUser(theirs, origin, user.email, false);
  expect(back.status, 'an activated user can sign in again').toBe(200);
  await theirs.dispose();
  expect(errors).toEqual([]);
});

test('USR-05 · your own row cannot be deactivated, the last admin cannot be demoted, and an admin resets two-step sign-in', async ({
  signedInAs,
  playwright,
  baseURL,
}) => {
  const page = await signedInAs('admin');
  const errors = consoleErrors(page);
  await page.goto('/settings/users');
  const mine = await rowOf(page, 'Alex Admin');
  await mine.getByRole('button', {name: 'Actions for Alex Admin'}).click();
  await expect(
    page.getByRole('menuitem', {name: 'Deactivate'}),
  ).toHaveAttribute('aria-disabled', 'true');
  await page.keyboard.press('Escape');

  // Alex is the only active admin of Mustang Motors: the edit is refused and nothing changes.
  await mine.getByRole('button', {name: 'Actions for Alex Admin'}).click();
  await page.getByRole('menuitem', {name: 'Edit'}).click();
  const edit = page.getByRole('dialog', {name: 'Edit Alex Admin'});
  await edit.getByLabel('Role').click();
  await page.getByRole('option', {name: 'Manager', exact: true}).click();
  await edit.getByRole('button', {name: 'Save changes'}).click();
  await expect(edit.getByText(/at least one active admin/)).toBeVisible();
  await edit.getByRole('button', {name: 'Cancel'}).click();
  const stillAdmin = await rowOf(page, 'Alex Admin');
  await expect(stillAdmin.getByText('Admin', {exact: true})).toBeVisible();

  // Reset a new user's factor: they enroll again at the next sign-in.
  const user = await addThroughApi(page, 'usr05');
  const origin = new URL(baseURL as string).origin;
  const theirs = await playwright.request.newContext({
    baseURL,
    extraHTTPHeaders: {'X-Forwarded-For': '10.79.0.5'},
  });
  await signInNewUser(theirs, origin, user.email);
  await theirs.post('/api/v1/auth/logout', {headers: {Origin: origin}});
  const next = await theirs.post('/api/v1/auth/login', {
    data: {email: user.email, password: NEW_PASSWORD},
    headers: {Origin: origin},
  });
  expect(
    (await next.json()).next,
    'after enrolling, sign-in asks for a code',
  ).toBe('mfa');
  await theirs.post('/api/v1/auth/logout', {headers: {Origin: origin}});

  const row = await rowOf(page, `USR05 ${LAST}`);
  await expect(row.getByText('On', {exact: true})).toBeVisible();
  await row.getByRole('button', {name: `Actions for ${user.name}`}).click();
  await page.getByRole('menuitem', {name: 'Reset two-step sign-in'}).click();
  const confirm = page.getByRole('alertdialog', {
    name: `Reset two-step sign-in for ${user.name}?`,
  });
  await confirm.getByRole('button', {name: `Reset for ${user.name}`}).click();
  await expect(
    page.getByText(`Two-step sign-in was reset for ${user.name}.`),
  ).toBeVisible();
  await expect(
    (await rowOf(page, `USR05 ${LAST}`)).getByText('Not set up'),
  ).toBeVisible();

  const after = await theirs.post('/api/v1/auth/login', {
    data: {email: user.email, password: NEW_PASSWORD},
    headers: {Origin: origin},
  });
  expect((await after.json()).next, 'they enroll again').toBe('enroll');
  await theirs.dispose();
  // The refused edit above answers 409 on purpose; the browser logs it.
  expect(errors.filter((e) => !/status of 409/.test(e))).toEqual([]);
});
