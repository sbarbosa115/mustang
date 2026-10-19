import {consoleErrors, expect, test} from './support/test';

test('PUB-01 · the sign-in page loads for a visitor', async ({page}) => {
  const errors = consoleErrors(page);
  await page.goto('/');

  await expect(page).toHaveURL(/\/login$/);
  await expect(
    page.getByRole('heading', {name: 'Sign in to Mustang'}),
  ).toBeVisible();
  expect(errors).toEqual([]);
});
