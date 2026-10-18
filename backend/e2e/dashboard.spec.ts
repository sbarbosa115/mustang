import {consoleErrors, expect, test} from './support/test';

/** KPI-01 – 04. Read-only: the dashboard changes nothing, so the seed stays as it was. */

const TILES = [
  'First response time',
  'Leads followed up in 24 hours',
  'Lead to contact',
  'Contact to appointment',
  'Appointment to show',
  'Show to sale',
  'Units per salesperson',
];

test.describe('desktop', () => {
  test.use({viewport: {width: 1280, height: 800}});

  test('KPI-01 · a manager sees seven KPIs with their change and the funnel', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    const errors = consoleErrors(page);
    await page.goto('/dashboard');

    await expect(
      page.getByRole('heading', {level: 1, name: 'Dashboard'}),
    ).toBeVisible();
    await expect(page.getByText('From lead to sale')).toBeVisible();
    for (const label of TILES) {
      await expect(
        page.locator('[data-slot="card"]', {hasText: label}).first(),
      ).toBeVisible();
    }
    await expect(
      page.getByText(/Compared with \d{4}-\d{2}-\d{2} to/),
    ).toBeVisible();
    await expect(
      page.getByRole('button', {name: 'Last 30 days'}),
    ).toHaveAttribute('aria-pressed', 'true');
    await expect(
      page.getByRole('img', {name: /leads, .* contacted/}),
    ).toBeVisible();

    await page.getByRole('button', {name: 'Show as table'}).click();
    await expect(page.getByRole('row', {name: /^Leads/})).toBeVisible();
    await expect(page.getByRole('row', {name: /Sold/})).toBeVisible();
    expect(errors).toEqual([]);
  });

  test('KPI-02 · picking a shorter period asks for it, and a backwards range is refused', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    await page.goto('/dashboard');
    await expect(page.getByText('From lead to sale')).toBeVisible();

    const asked = page.waitForRequest(
      (r) =>
        r.url().includes('/api/v1/reports/kpis?') && r.url().includes('from='),
    );
    await page.getByRole('button', {name: 'Last 7 days'}).click();
    await asked;
    await expect(page).toHaveURL(/from=\d{4}-\d{2}-\d{2}&to=/);
    await expect(
      page.getByRole('button', {name: 'Last 7 days'}),
    ).toHaveAttribute('aria-pressed', 'true');

    await page.getByLabel('From').fill('2000-02-01');
    await page.getByLabel('To').fill('2000-01-01');
    await expect(
      page.getByText('Pick a start that is not after the end.'),
    ).toBeVisible();
  });

  test('KPI-03 · a period with no leads says "No data", never 0', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('manager');
    await page.goto('/dashboard?from=2000-01-01&to=2000-01-31');

    await expect(page.getByText('No leads in this period.')).toBeVisible();
    await expect(page.getByText('No data')).toHaveCount(6);
    await expect(
      page.getByText('2000-01-01 to 2000-01-31').first(),
    ).toBeVisible();
  });

  test('KPI-04 · reps cannot open the dashboard or read the KPIs', async ({
    signedInAs,
  }) => {
    const page = await signedInAs('sales');
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/today$/);

    const api = await page.request.get('/api/v1/reports/kpis');
    expect(api.status()).toBe(403);
  });
});
