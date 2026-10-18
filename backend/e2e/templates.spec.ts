import {type Page} from '@playwright/test';
import {consoleErrors, expect, test} from './support/test';

/**
 * TPL-01 – 04. Cases create templates of their own (named TPL-0x …) and remove them again, so the three seeded
 * templates stay as the seed made them.
 */

async function openNew(page: Page) {
  await page.goto('/settings/templates');
  await page.getByRole('button', {name: 'New template'}).first().click();
  return page.getByRole('dialog', {name: 'New template'});
}

async function chooseChannel(page: Page, name: string) {
  await page.getByRole('dialog').getByLabel('Channel', {exact: true}).click();
  await page.getByRole('option', {name, exact: true}).click();
}

async function removeByApi(page: Page, name: string) {
  const list = await page.request.get('/api/v1/templates');
  const {items} = (await list.json()) as {
    items: Array<{id: string; name: string}>;
  };
  for (const item of items.filter((i) => i.name === name)) {
    await page.request.delete(`/api/v1/templates/${item.id}`, {
      headers: {Origin: new URL(page.url()).origin},
    });
  }
}

test.describe('desktop', () => {
  test.use({viewport: {width: 1280, height: 800}});

  test('TPL-01 · a manager writes a text template with the placeholder helper', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    const errors = consoleErrors(page);
    const dialog = await openNew(page);
    const save = dialog.getByRole('button', {name: 'Save template'});
    await expect(save).toBeDisabled();

    await dialog.getByLabel('Name', {exact: true}).fill('TPL-01 Greeting');
    await dialog.getByLabel('Message', {exact: true}).fill('Hi ');
    await dialog.getByRole('button', {name: 'Insert {first_name}'}).click();
    await dialog.getByLabel('Message', {exact: true}).pressSequentially(', ');
    await dialog.getByRole('button', {name: 'Insert {rep_name}'}).click();
    await expect(dialog.getByLabel('Message', {exact: true})).toHaveValue(
      'Hi {first_name}, {rep_name}',
    );
    await expect(dialog.getByRole('region', {name: 'Preview'})).toContainText(
      'Hi Maria, Sam',
    );

    await save.click();
    await expect(page.getByText('TPL-01 Greeting was saved.')).toBeVisible();
    const row = page.getByRole('row', {name: /TPL-01 Greeting/});
    await expect(row).toContainText('Text');
    await expect(row).toContainText('Active');
    await expect(row).toContainText('Hi {first_name}, {rep_name}');
    expect(errors).toEqual([]);
    await removeByApi(page, 'TPL-01 Greeting');
  });

  test('TPL-02 · an email needs a subject and an unknown placeholder is refused', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    const dialog = await openNew(page);
    const save = dialog.getByRole('button', {name: 'Save template'});
    await chooseChannel(page, 'Email');
    await dialog.getByLabel('Name', {exact: true}).fill('TPL-02 Visit');
    await dialog
      .getByLabel('Message', {exact: true})
      .fill('Hello {first_name}');
    await expect(save, 'an email without a subject').toBeDisabled();
    await dialog.getByLabel('Subject', {exact: true}).fill('Your visit');
    await expect(save).toBeEnabled();

    await dialog.getByLabel('Message', {exact: true}).fill('Hello {pet_name}');
    await expect(
      dialog.getByText(/Unknown placeholder: \{pet_name\}/),
    ).toBeVisible();
    await expect(save).toBeDisabled();

    const refused = await page.request.post('/api/v1/templates', {
      data: {name: 'TPL-02 Api', channel: 'text', body: 'Hi {pet_name}'},
      headers: {Origin: new URL(page.url()).origin},
    });
    expect(refused.status()).toBe(422);
    expect(((await refused.json()) as {error: string}).error).toBe(
      'unknown_placeholder',
    );

    await dialog
      .getByLabel('Message', {exact: true})
      .fill('Hello {first_name}');
    await save.click();
    await expect(page.getByText('TPL-02 Visit was saved.')).toBeVisible();
    await expect(page.getByRole('row', {name: /TPL-02 Visit/})).toContainText(
      'Email',
    );
    await removeByApi(page, 'TPL-02 Visit');
  });

  test('TPL-03 · edit, deactivate and delete a template (the dialog names it)', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    const dialog = await openNew(page);
    await dialog.getByLabel('Name', {exact: true}).fill('TPL-03 Follow up');
    await dialog.getByLabel('Message', {exact: true}).fill('Checking in');
    await dialog.getByRole('button', {name: 'Save template'}).click();
    await expect(page.getByText('TPL-03 Follow up was saved.')).toBeVisible();

    await page
      .getByRole('button', {name: 'Edit TPL-03 Follow up'})
      .first()
      .click();
    const edit = page.getByRole('dialog', {name: 'Edit template'});
    await expect(edit.getByLabel('Channel', {exact: true})).toBeDisabled();
    await edit.getByLabel('Name', {exact: true}).fill('TPL-03 Follow up v2');
    await edit.getByLabel('Active', {exact: true}).click();
    await edit.getByRole('button', {name: 'Save template'}).click();
    const row = page.getByRole('row', {name: /TPL-03 Follow up v2/});
    await expect(row).toContainText('Inactive');

    await page
      .getByRole('button', {name: 'Delete TPL-03 Follow up v2'})
      .first()
      .click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('Delete TPL-03 Follow up v2?');
    await confirm.getByRole('button', {name: 'Cancel'}).click();
    await expect(row).toBeVisible();

    await page
      .getByRole('button', {name: 'Delete TPL-03 Follow up v2'})
      .first()
      .click();
    await page
      .getByRole('alertdialog')
      .getByRole('button', {name: 'Delete template'})
      .click();
    await expect(
      page.getByText('TPL-03 Follow up v2 was deleted.'),
    ).toBeVisible();
    await expect(row).toHaveCount(0);
  });
});

test('TPL-04 · reps only read templates and another dealership sees none', async ({
  signedInAs,
}) => {
  const rep = await signedInAs('sales');
  await rep.goto('/settings');
  await expect(
    rep.getByRole('link', {name: 'Templates', exact: true}),
  ).toHaveCount(0);
  const origin = new URL(rep.url()).origin;
  const list = await rep.request.get('/api/v1/templates');
  expect(list.status(), 'reps read templates for the composer').toBe(200);
  const {items} = (await list.json()) as {
    items: Array<{id: string; name: string}>;
  };
  expect(items.map((i) => i.name)).toContain('First reply');
  const write = await rep.request.post('/api/v1/templates', {
    data: {name: 'TPL-04 Nope', channel: 'text', body: 'Hi'},
    headers: {Origin: origin},
  });
  expect(write.status(), 'a rep cannot create').toBe(403);

  const other = await signedInAs('other');
  const theirs = await other.request.get('/api/v1/templates');
  const names = ((await theirs.json()) as {items: Array<{name: string}>}).items;
  expect(names.map((i) => i.name)).not.toContain('First reply');
  const first = items.find((i) => i.name === 'First reply')!;
  const peek = await other.request.get(`/api/v1/templates/${first.id}`);
  expect(peek.status(), 'another dealership gets 404, never 403').toBe(404);
});
